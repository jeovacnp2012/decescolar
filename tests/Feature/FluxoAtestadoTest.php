<?php

namespace Tests\Feature;

use App\Mail\AtestadoProntoMail;
use App\Models\AtestadoFilho;
use App\Models\AtestadoPedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FluxoAtestadoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'chamados.url' => 'https://chamados.test/api/v1',
            'chamados.token' => 'token-chamados',
            'decescolar.api_token' => 'token-decescolar',
        ]);
        Cache::flush();
        Storage::fake('local');
    }

    private function fakeChamados(): void
    {
        Http::fake([
            '*/vaccination/units' => Http::response(['data' => [['id' => 8, 'name' => 'PSF PARQUE DO SOL'], ['id' => 4, 'name' => 'ESF ALTO DA COLINA']]]),
            '*/vaccination/requests' => Http::response(['data' => [['protocol' => 'ATV000001'], ['protocol' => 'ATV000002']]], 201),
        ]);
    }

    private function dadosValidos(array $extra = []): array
    {
        return array_merge([
            'nome' => 'Maria da Silva', 'cpf' => '529.982.247-25', 'telefone' => '(65) 99999-1234',
            'email' => 'maria@exemplo.com', 'cep' => '78450-000', 'escola' => 'Escola Municipal X',
            'unidade_id' => 8, 'pedir_atestado' => '1',
            'filhos' => [
                ['nome' => 'João', 'serie' => '3º ano', 'data_nascimento' => '2017-05-10', 'nome_mae' => 'Maria da Silva'],
                ['nome' => 'Ana', 'serie' => '1º ano', 'data_nascimento' => '2019-02-01', 'nome_mae' => 'Maria da Silva'],
            ],
        ], $extra);
    }

    public function test_formulario_lista_unidades_vindas_da_api(): void
    {
        $this->fakeChamados();
        $this->get('/')->assertOk()->assertSee('PSF PARQUE DO SOL')->assertSee('Peço atestado vacinal');
    }

    public function test_pedido_com_varios_filhos_e_enviado_ao_chamados_e_guarda_protocolos(): void
    {
        $this->fakeChamados();
        $resp = $this->post('/pedido', $this->dadosValidos());

        $pedido = AtestadoPedido::firstOrFail();
        $resp->assertRedirect(route('pedido.show', $pedido->uuid));
        $this->assertSame(['ATV000001', 'ATV000002'], $pedido->filhos()->orderBy('id')->pluck('protocolo')->all());
        $this->assertSame('PSF PARQUE DO SOL', $pedido->unidade_nome);
        // CPF nunca fica em texto puro no banco
        $this->assertStringNotContainsString('52998224725', (string) \DB::table('atestado_pedidos')->value('cpf'));

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/vaccination/requests')
            && $r['guardian']['cpf'] === '52998224725' && count($r['children']) === 2 && $r['sector_id'] === 8);
    }

    public function test_cpf_invalido_unidade_fora_da_lista_e_sem_checkbox_sao_recusados(): void
    {
        $this->fakeChamados();
        $this->post('/pedido', $this->dadosValidos(['cpf' => '111.111.111-11', 'unidade_id' => 999]))
            ->assertSessionHasErrors(['cpf', 'unidade_id']);
        $this->post('/pedido', $this->dadosValidos(['pedir_atestado' => null]))->assertSessionHasErrors('pedir_atestado');
        $this->assertSame(0, AtestadoPedido::count());
    }

    public function test_falha_no_chamados_nao_deixa_pedido_pela_metade(): void
    {
        Http::fake([
            '*/vaccination/units' => Http::response(['data' => [['id' => 8, 'name' => 'PSF PARQUE DO SOL']]]),
            '*/vaccination/requests' => Http::response(['message' => 'erro'], 500),
        ]);
        $this->post('/pedido', $this->dadosValidos())->assertSessionHasErrors('geral');
        $this->assertSame(0, AtestadoPedido::count());
    }

    public function test_api_exige_token(): void
    {
        $this->postJson('/api/v1/atestados/ATV000001/pronto')->assertStatus(401);
        $this->withToken('errado')->postJson('/api/v1/atestados/ATV000001/pronto')->assertStatus(401);
    }

    private function enviarPdf(string $protocolo, string $conteudo = "%PDF-1.4
%fake
")
    {
        $pdf = UploadedFile::fake()->createWithContent('a.pdf', $conteudo);

        return $this->withToken('token-decescolar')->post("/api/v1/atestados/{$protocolo}/pronto", [
            'certificate' => $pdf, 'protocol' => $protocolo, 'child_name' => 'João', 'guardian_cpf' => '52998224725',
            'unit' => 'PSF PARQUE DO SOL', 'sent_at' => now()->toIso8601String(),
        ], ['Accept' => 'application/json']);
    }

    public function test_chamados_entrega_pdf_responsavel_baixa_e_decescolar_avisa_atendido(): void
    {
        $this->fakeChamados();
        $this->post('/pedido', $this->dadosValidos());
        Mail::fake();

        $this->enviarPdf('ATV000001')->assertOk()->assertJsonPath('data.status', 'pronto');
        // e-mail é do chamados: o decescolar não envia por padrão
        Mail::assertNothingSent();

        $filho = AtestadoFilho::where('protocolo', 'ATV000001')->first();
        $this->get(route('atestado.show', $filho->token))->assertOk()->assertSee('Baixar declaração vacinal');

        Http::fake(['*/vaccination/requests/ATV000001/served*' => Http::response(['data' => ['status' => 'served']])]);
        $this->get(route('atestado.baixar', $filho->token))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $filho->refresh();
        $this->assertSame('baixado', $filho->status);
        $this->assertNotNull($filho->atendido_avisado_em);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'ATV000001/served') && str_contains($r->url(), 'cpf=52998224725'));
        // o outro irmão continua aguardando
        $this->assertSame('aguardando', AtestadoFilho::where('protocolo', 'ATV000002')->value('status'));
    }

    public function test_reenvio_do_mesmo_protocolo_substitui_o_pdf(): void
    {
        $this->fakeChamados();
        $this->post('/pedido', $this->dadosValidos());

        $this->enviarPdf('ATV000001', "%PDF-1.4
primeiro")->assertOk();
        $antigo = AtestadoFilho::where('protocolo', 'ATV000001')->value('arquivo');
        $this->enviarPdf('ATV000001', "%PDF-1.4
segundo")->assertOk();
        $novo = AtestadoFilho::where('protocolo', 'ATV000001')->value('arquivo');

        $this->assertNotSame($antigo, $novo);
        Storage::disk('local')->assertMissing($antigo);
        $this->assertSame("%PDF-1.4
segundo", Storage::disk('local')->get($novo));
    }

    public function test_falha_ao_avisar_atendido_nao_impede_o_download_e_o_comando_repete(): void
    {
        $this->fakeChamados();
        $this->post('/pedido', $this->dadosValidos());
        $this->enviarPdf('ATV000001')->assertOk();
        $filho = AtestadoFilho::where('protocolo', 'ATV000001')->first();

        Http::fake(['*/vaccination/requests/ATV000001/served*' => Http::sequence()->push([], 503)->push(['data' => []])]);
        $this->get(route('atestado.baixar', $filho->token))->assertOk();
        $this->assertNull($filho->refresh()->atendido_avisado_em);

        $this->artisan('atestados:avisar-atendidos')->assertSuccessful();
        $this->assertNotNull($filho->refresh()->atendido_avisado_em);
    }

    public function test_pedido_sem_pdf_e_protocolo_inexistente_sao_recusados(): void
    {
        $this->fakeChamados();
        $this->post('/pedido', $this->dadosValidos());
        $this->withToken('token-decescolar')->postJson('/api/v1/atestados/ATV000001/pronto')->assertStatus(422);
        $this->enviarPdf('ATV999999')->assertStatus(404);
    }

    public function test_arquivo_que_nao_e_pdf_e_recusado(): void
    {
        $this->fakeChamados();
        $this->post('/pedido', $this->dadosValidos());
        $falso = UploadedFile::fake()->createWithContent('a.pdf', 'isto nao e pdf');
        $this->withToken('token-decescolar')->post('/api/v1/atestados/ATV000001/pronto', ['certificate' => $falso], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_consulta_exige_cpf_e_protocolo_corretos(): void
    {
        $this->fakeChamados();
        $this->post('/pedido', $this->dadosValidos());
        $filho = AtestadoFilho::where('protocolo', 'ATV000001')->first();

        $this->post('/consultar', ['cpf' => '529.982.247-25', 'protocolo' => 'atv000001'])->assertRedirect(route('atestado.show', $filho->token));
        $this->post('/consultar', ['cpf' => '111.444.777-35', 'protocolo' => 'ATV000001'])->assertSessionHasErrors('protocolo');
    }

    public function test_responsavel_cancela_pedido_apaga_pdf_e_avisa_a_unidade(): void
    {
        $this->fakeChamados();
        $this->post('/pedido', $this->dadosValidos());
        $this->enviarPdf('ATV000001')->assertOk();
        $filho = AtestadoFilho::where('protocolo', 'ATV000001')->first();
        $arquivo = $filho->arquivo;
        Storage::disk('local')->assertExists($arquivo);
        Http::fake(['*/vaccination/requests/*/cancel*' => Http::response([], 200)]);

        $this->post(route('atestado.cancelar', $filho->token))->assertRedirect(route('atestado.show', $filho->token));

        $filho->refresh();
        $this->assertSame('cancelado', $filho->status);
        $this->assertNotNull($filho->cancelado_em);
        $this->assertNotNull($filho->cancelamento_avisado_em);
        $this->assertNull($filho->arquivo);
        Storage::disk('local')->assertMissing($arquivo);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/vaccination/requests/ATV000001/cancel') && str_contains($r->url(), 'cpf=52998224725'));
        $this->get(route('atestado.show', $filho->token))->assertOk()->assertSee('Pedido cancelado')->assertDontSee('Baixar declaração');
        $this->get(route('atestado.baixar', $filho->token))->assertNotFound();
        // o irmão não é afetado
        $this->assertSame('aguardando', AtestadoFilho::where('protocolo', 'ATV000002')->value('status'));
    }

    public function test_cancelamento_com_chamados_fora_do_ar_fica_pendente_e_o_comando_reenvia(): void
    {
        $this->fakeChamados();
        $this->post('/pedido', $this->dadosValidos());
        $filho = AtestadoFilho::where('protocolo', 'ATV000002')->first();

        Http::fake(['*/vaccination/requests/*/cancel*' => Http::sequence()->push([], 503)->push([], 200)]);
        $this->post(route('atestado.cancelar', $filho->token))->assertRedirect();
        $filho->refresh();
        $this->assertSame('cancelado', $filho->status);
        $this->assertNull($filho->cancelamento_avisado_em);

        $this->artisan('atestados:avisar-atendidos')->assertSuccessful();
        $this->assertNotNull($filho->fresh()->cancelamento_avisado_em);
    }

    public function test_404_do_chamados_para_protocolo_excluido_encerra_o_reenvio_mas_rota_inexistente_nao(): void
    {
        $this->fakeChamados();
        $this->post('/pedido', $this->dadosValidos());
        $a = AtestadoFilho::where('protocolo', 'ATV000001')->first();
        $b = AtestadoFilho::where('protocolo', 'ATV000002')->first();

        Http::fake(['*/vaccination/requests/*/cancel*' => Http::sequence()
            ->push(['message' => 'The route api/v1/vaccination/requests/ATV000001/cancel could not be found.'], 404)
            ->push(['message' => 'Pedido não encontrado.'], 404)
            ->push(['message' => 'Já atendido.'], 409)]);

        // rota ainda não publicada no chamados: continua pendente
        $this->post(route('atestado.cancelar', $a->token));
        $this->assertNull($a->fresh()->cancelamento_avisado_em);

        // pedido já excluído no chamados: encerra o reenvio
        $this->post(route('atestado.cancelar', $b->token));
        $this->assertNotNull($b->fresh()->cancelamento_avisado_em);

        // 409 (unidade já atendeu): também encerra o reenvio automático
        $this->artisan('atestados:avisar-atendidos')->assertSuccessful();
        $this->assertNotNull($a->fresh()->cancelamento_avisado_em);
    }

    public function test_unidade_exclui_no_chamados_e_decescolar_marca_cancelado_com_motivo_de_forma_idempotente(): void
    {
        $this->fakeChamados();
        $this->postJson('/api/v1/atestados/ATV000001/cancelado')->assertStatus(401);
        $this->post('/pedido', $this->dadosValidos());
        $this->enviarPdf('ATV000001')->assertOk();
        $filho = AtestadoFilho::where('protocolo', 'ATV000001')->first();
        $arquivo = $filho->arquivo;

        $corpo = ['protocol' => 'ATV000001', 'status' => 'canceled', 'message' => 'Pedido removido do sistema. Por favor, refaça o pedido.',
            'reason' => 'Dados do filho incorretos', 'canceled_at' => now()->toIso8601String()];
        $this->withToken('token-decescolar')->postJson('/api/v1/atestados/ATV000001/cancelado', $corpo)
            ->assertOk()->assertJsonPath('data.status', 'cancelado');
        $this->withToken('token-decescolar')->postJson('/api/v1/atestados/atv000001/cancelado', $corpo)->assertOk(); // repetir é inofensivo
        $this->withToken('token-decescolar')->postJson('/api/v1/atestados/ATV999999/cancelado', $corpo)->assertOk(); // desconhecido também é 2xx

        $filho->refresh();
        $this->assertSame('unidade', $filho->cancelado_por);
        $this->assertSame('Dados do filho incorretos', $filho->cancelado_motivo);
        $this->assertNotNull($filho->cancelamento_avisado_em);
        Storage::disk('local')->assertMissing($arquivo);
        $this->get(route('atestado.show', $filho->token))->assertOk()
            ->assertSee('Pedido removido do sistema')->assertSee('Dados do filho incorretos')->assertSee('Fazer novo pedido');
        $this->enviarPdf('ATV000001')->assertStatus(409);
    }

    public function test_pedido_ja_baixado_nao_pode_ser_cancelado_e_pdf_de_cancelado_e_recusado(): void
    {
        $this->fakeChamados();
        $this->post('/pedido', $this->dadosValidos());
        $this->enviarPdf('ATV000001')->assertOk();
        $baixado = AtestadoFilho::where('protocolo', 'ATV000001')->first();
        Http::fake(['*/vaccination/requests/*/served*' => Http::response([], 200), '*/vaccination/requests/*/cancel*' => Http::response([], 200)]);
        $this->get(route('atestado.baixar', $baixado->token))->assertOk();

        $this->post(route('atestado.cancelar', $baixado->token))->assertRedirect()->assertSessionHas('aviso');
        $this->assertSame('baixado', $baixado->fresh()->status);

        $cancelado = AtestadoFilho::where('protocolo', 'ATV000002')->first();
        $this->post(route('atestado.cancelar', $cancelado->token));
        $this->enviarPdf('ATV000002')->assertStatus(409);
        $this->assertNull($cancelado->fresh()->arquivo);
    }
}
