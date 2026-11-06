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
            // um protocolo por item de children[] (ATV000001, ATV000002...)
            '*/vaccination/requests' => fn ($r) => Http::response(['data' => collect($r['children'])->keys()
                ->map(fn ($i) => ['protocol' => 'ATV'.str_pad($i + 1, 6, '0', STR_PAD_LEFT)])->all()], 201),
        ]);
    }

    private function endereco(): array
    {
        return ['cep' => '78450-000', 'logradouro' => 'Rua das Acácias', 'numero' => '123', 'complemento' => '', 'bairro' => 'Centro', 'cidade' => 'Nova Mutum', 'uf' => 'mt'];
    }

    private function dadosValidos(array $extra = []): array
    {
        return array_merge([
            'tipo' => 'filhos',
            'nome' => 'Maria da Silva', 'cpf' => '529.982.247-25', 'telefone' => '(65) 99999-1234',
            'vinculo' => 'mae', 'data_nascimento_responsavel' => now()->subYears(30)->format('Y-m-d'),
            'email' => 'maria@exemplo.com', 'acs_nome' => 'Joana Souza',
            'unidade_id' => 8,
            ...$this->endereco(),
            'filhos' => [
                ['nome' => 'João', 'serie' => '3º ano', 'data_nascimento' => '2017-05-10', 'nome_mae' => 'Maria da Silva'],
                ['nome' => 'Ana', 'serie' => '1º ano', 'data_nascimento' => '2019-02-01', 'nome_mae' => 'Maria da Silva'],
            ],
        ], $extra);
    }

    public function test_formulario_lista_unidades_vindas_da_api(): void
    {
        $this->fakeChamados();
        $this->get('/solicitar/formulario')->assertOk()->assertSee('PSF PARQUE DO SOL')
            ->assertSee('Para quem é o atestado?')->assertSee('Para filhos')->assertSee('Responsável legal')->assertSee('Meu atestado')
            ->assertSee('Para um funcionário de empresa/indústria')->assertSee('Nome do ACS')
            ->assertDontSee('Nome da escola')->assertDontSee('Não sei a minha unidade');
    }

    public function test_triagem_da_unidade_pergunta_e_orienta_quem_nao_sabe(): void
    {
        $this->get('/solicitar')->assertOk()->assertSee('Está devidamente vinculado a uma unidade de saúde?')
            ->assertSee(route('triagem.nome'), false)->assertSee(route('triagem.procure'), false);
        $this->get(route('triagem.nome'))->assertOk()->assertSee('Sabe o nome de sua unidade básica de saúde?')
            ->assertSee(route('pedido.formulario'), false)->assertSee(route('triagem.procure'), false);
        $this->get(route('triagem.procure'))->assertOk()
            ->assertSee('Favor procure uma unidade de saúde mais próxima para adquirir seu atestado vacinal.')
            ->assertSee('OK')->assertSee(route('inicio'), false);
    }

    public function test_tela_inicial_tem_os_dois_botoes_na_ordem_pedida(): void
    {
        $r = $this->get('/')->assertOk()->assertSee('Já fiz um pedido — consultar / baixar')->assertSee('Solicitar atestado');
        $html = $r->getContent();
        $this->assertLessThan(strpos($html, 'Solicitar atestado</a>'), strpos($html, 'Já fiz um pedido'));
        $r->assertSee(route('consulta.form'), false)->assertSee(route('pedido.create'), false);
    }

    public function test_regra_de_idade_do_responsavel_e_parentesco_do_outro(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class); // este teste faz mais de 10 envios
        $n = 0;
        Http::fake([
            '*/vaccination/units' => Http::response(['data' => [['id' => 8, 'name' => 'PSF PARQUE DO SOL']]]),
            '*/vaccination/requests' => function ($r) use (&$n) {
                $dados = [];
                foreach ($r['children'] as $_) {
                    $dados[] = ['protocol' => 'ATV'.str_pad(++$n, 6, '0', STR_PAD_LEFT)];
                }

                return Http::response(['data' => $dados], 201);
            },
        ]);
        $nasc = fn (int $anos, int $dias = 0) => now()->subYears($anos)->addDays($dias)->format('Y-m-d');

        // pai/mãe: 15 anos completos pode; 14 anos não
        $this->post('/pedido', $this->dadosValidos(['vinculo' => 'pai', 'data_nascimento_responsavel' => $nasc(14, 30)]))
            ->assertSessionHasErrors('data_nascimento_responsavel');
        $this->assertSame(0, AtestadoPedido::count());
        $this->post('/pedido', $this->dadosValidos(['vinculo' => 'pai', 'data_nascimento_responsavel' => $nasc(15)]))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, AtestadoPedido::count());

        // responsável legal: só a partir de 18; parentesco é opcional
        $this->post('/pedido', $this->dadosValidos(['tipo' => 'responsavel_legal', 'vinculo' => null, 'parentesco' => 'tia', 'data_nascimento_responsavel' => $nasc(17, 200)]))
            ->assertSessionHasErrors('data_nascimento_responsavel');
        $this->assertSame(1, AtestadoPedido::count());
        $this->post('/pedido', $this->dadosValidos(['tipo' => 'responsavel_legal', 'vinculo' => null, 'parentesco' => '', 'data_nascimento_responsavel' => $nasc(18)]))
            ->assertSessionHasNoErrors();

        // "meu atestado": 15 anos completos pode; 14 não (um único beneficiário, sem lista de filhos)
        $proprio = ['tipo' => 'proprio', 'vinculo' => null, 'filhos' => null];
        $this->post('/pedido', $this->dadosValidos($proprio + ['data_nascimento_responsavel' => $nasc(14, 30)]))
            ->assertSessionHasErrors('data_nascimento_responsavel');
        $this->post('/pedido', $this->dadosValidos($proprio + ['data_nascimento_responsavel' => $nasc(15)]))->assertSessionHasNoErrors();

        // tipo "filhos": só pai ou mãe (responsável legal e outro viraram tipo próprio)
        $this->post('/pedido', $this->dadosValidos(['vinculo' => null]))->assertSessionHasErrors('vinculo');
        $this->post('/pedido', $this->dadosValidos(['vinculo' => 'outro']))->assertSessionHasErrors('vinculo');
        $this->post('/pedido', $this->dadosValidos(['vinculo' => 'responsavel_legal']))->assertSessionHasErrors('vinculo');
        $this->post('/pedido', $this->dadosValidos(['tipo' => 'xyz']))->assertSessionHasErrors('tipo');
        // data de nascimento é obrigatória; ACS e escola não
        $this->post('/pedido', $this->dadosValidos(['data_nascimento_responsavel' => '']))->assertSessionHasErrors('data_nascimento_responsavel');
        $this->post('/pedido', $this->dadosValidos(['acs_nome' => '']))->assertSessionHasNoErrors();
    }

    public function test_dados_novos_sao_gravados_com_data_criptografada_e_enviados_ao_chamados(): void
    {
        $this->fakeChamados();
        $this->post('/pedido', $this->dadosValidos(['tipo' => 'responsavel_legal', 'vinculo' => null, 'parentesco' => 'avó', 'data_nascimento_responsavel' => '1960-02-03']));

        $p = AtestadoPedido::firstOrFail();
        $this->assertSame('responsavel_legal', $p->tipo);
        $this->assertSame('avó', $p->parentesco);
        $this->assertSame('1960-02-03', $p->data_nascimento_responsavel);
        $this->assertSame('Joana Souza', $p->acs_nome);
        $this->assertStringNotContainsString('1960', (string) \DB::table('atestado_pedidos')->value('data_nascimento_responsavel'));

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/vaccination/requests')
            && $r['guardian']['type'] === 'responsavel_legal'
            && $r['guardian']['relationship'] === 'responsavel_legal' && $r['guardian']['relationship_other'] === 'avó'
            && $r['guardian']['birth_date'] === '1960-02-03' && $r['guardian']['acs_name'] === 'Joana Souza'
            && ! array_key_exists('school', $r['guardian']));
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
        $this->post('/pedido', $this->dadosValidos(['filhos' => []]))->assertSessionHasErrors('filhos');
        $this->post('/pedido', $this->dadosValidos(['logradouro' => '', 'numero' => '', 'bairro' => '', 'cidade' => '', 'uf' => 'M']))
            ->assertSessionHasErrors(['logradouro', 'numero', 'bairro', 'cidade', 'uf']);
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

    private function dadosEmpresa(array $extra = []): array
    {
        return array_merge([
            'tipo' => 'empresa', 'nome' => 'Indústria Exemplo Ltda', 'cnpj' => '12.345.678/0001-95', 'telefone' => '(65) 3333-4444',
            'email' => 'rh@industria.com.br', ...$this->endereco(), 'acs_nome' => '',
            'funcionarios' => [
                ['nome' => 'José Almeida', 'data_nascimento' => '1990-08-12', 'cpf' => '529.982.247-25', 'nome_mae' => 'Ana Almeida', 'unidade_id' => 8],
                ['nome' => 'Paulo Lima', 'data_nascimento' => '1985-01-30', 'cpf' => '111.444.777-35', 'nome_mae' => 'Rita Lima', 'unidade_id' => 4],
            ],
        ], $extra);
    }

    public function test_meu_atestado_envia_a_propria_pessoa_como_unico_beneficiario(): void
    {
        $this->fakeChamados();
        $this->post('/pedido', $this->dadosValidos(['tipo' => 'proprio', 'vinculo' => null, 'filhos' => null, 'nome' => 'Ana Paula Souza']))
            ->assertSessionHasNoErrors();

        $p = AtestadoPedido::firstOrFail();
        $this->assertSame('proprio', $p->tipo);
        $this->assertSame(1, $p->filhos()->count());
        $f = $p->filhos()->first();
        $this->assertSame('Ana Paula Souza', $f->nome);
        $this->assertSame('52998224725', $f->cpf);
        $this->assertSame(8, (int) $f->unidade_id);
        $this->assertStringNotContainsString('52998224725', (string) \DB::table('atestado_filhos')->value('cpf'));

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/vaccination/requests')
            && $r['guardian']['type'] === 'proprio' && count($r['children']) === 1
            && $r['children'][0]['cpf'] === '52998224725' && $r['children'][0]['name'] === 'Ana Paula Souza'
            && ! isset($r['children'][0]['grade']) && ! isset($r['children'][0]['mother_name'])
            && $r['guardian']['state'] === 'MT' && $r['guardian']['street'] === 'Rua das Acácias' && $r['sector_id'] === 8);
    }

    public function test_empresa_usa_cnpj_uma_unidade_por_funcionario_e_consulta_pelo_cnpj(): void
    {
        $this->fakeChamados();
        $this->post('/pedido', $this->dadosEmpresa())->assertSessionHasNoErrors();

        $p = AtestadoPedido::firstOrFail();
        $this->assertSame('empresa', $p->tipo);
        $this->assertSame('12345678000195', $p->cnpj);
        $this->assertNull($p->cpf);
        $this->assertNull($p->unidade_id);
        $this->assertSame([8, 4], $p->filhos()->orderBy('id')->pluck('unidade_id')->map(fn ($v) => (int) $v)->all());
        $this->assertSame(['ATV000001', 'ATV000002'], $p->filhos()->orderBy('id')->pluck('protocolo')->all());

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/vaccination/requests')
            && $r['guardian']['type'] === 'empresa' && $r['guardian']['cnpj'] === '12345678000195' && $r['guardian']['name'] === 'Indústria Exemplo Ltda'
            && ! isset($r['guardian']['cpf']) && ! isset($r['guardian']['birth_date']) && ! isset($r['guardian']['relationship'])
            && ! isset($r['sector_id'])
            && $r['children'][0]['sector_id'] === 8 && $r['children'][1]['sector_id'] === 4
            && $r['children'][0]['cpf'] === '52998224725' && $r['children'][1]['cpf'] === '11144477735');

        // consulta pelo CNPJ (com ou sem máscara); CPF de funcionário não serve
        $filho = AtestadoFilho::where('protocolo', 'ATV000002')->first();
        $this->post('/consultar', ['cpf' => '12.345.678/0001-95', 'protocolo' => 'atv000002'])->assertRedirect(route('atestado.show', $filho->token));
        $this->post('/consultar', ['cpf' => '12345678000195', 'protocolo' => 'ATV000002'])->assertRedirect(route('atestado.show', $filho->token));
        $this->post('/consultar', ['cpf' => '111.444.777-35', 'protocolo' => 'ATV000002'])->assertSessionHasErrors('protocolo');
        $this->get(route('atestado.show', $filho->token))->assertOk()->assertSee('CNPJ 12.345.678/0001-95');

        // avisos ao chamados identificam a empresa por ?cnpj=
        $this->enviarPdf('ATV000001')->assertOk();
        Http::fake(['*/vaccination/requests/*/served*' => Http::response([], 200), '*/vaccination/requests/*/cancel*' => Http::response([], 200)]);
        $primeiro = AtestadoFilho::where('protocolo', 'ATV000001')->first();
        $this->get(route('atestado.baixar', $primeiro->token))->assertOk();
        Http::assertSent(fn ($r) => str_contains($r->url(), 'ATV000001/served') && str_contains($r->url(), 'cnpj=12345678000195') && ! str_contains($r->url(), 'cpf='));
        $this->post(route('atestado.cancelar', $filho->token));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'ATV000002/cancel') && str_contains($r->url(), 'cnpj=12345678000195'));
    }

    public function test_empresa_valida_cnpj_cpf_do_funcionario_unidade_e_repeticoes(): void
    {
        $this->fakeChamados();
        $this->post('/pedido', $this->dadosEmpresa(['cnpj' => '12.345.678/0001-96']))->assertSessionHasErrors('cnpj');
        $this->post('/pedido', $this->dadosEmpresa(['funcionarios' => []]))->assertSessionHasErrors('funcionarios');
        $f = $this->dadosEmpresa()['funcionarios'];
        $f[0]['cpf'] = '111.111.111-11';
        $f[1]['unidade_id'] = 999;
        $this->post('/pedido', $this->dadosEmpresa(['funcionarios' => $f]))->assertSessionHasErrors(['funcionarios.0.cpf', 'funcionarios.1.unidade_id']);
        $f = $this->dadosEmpresa()['funcionarios'];
        $f[1]['cpf'] = $f[0]['cpf'];
        $this->post('/pedido', $this->dadosEmpresa(['funcionarios' => $f]))->assertSessionHasErrors('funcionarios.1.cpf');
        $f = $this->dadosEmpresa()['funcionarios'];
        unset($f[0]['nome_mae']);
        $this->post('/pedido', $this->dadosEmpresa(['funcionarios' => $f]))->assertSessionHasErrors('funcionarios.0.nome_mae');
        $this->assertSame(0, AtestadoPedido::count());
    }

    public function test_cnpj_alfanumerico_e_validado(): void
    {
        $this->assertTrue(\App\Rules\Cnpj::valido('12.345.678/0001-95'));
        $this->assertFalse(\App\Rules\Cnpj::valido('12.345.678/0001-96'));
        $this->assertFalse(\App\Rules\Cnpj::valido('11.111.111/1111-11'));
        // 12 primeiros caracteres alfanuméricos, 2 dígitos verificadores calculados
        $base = '12ABC34501DE';
        $v = array_map(fn ($c) => ord($c) - 48, str_split($base));
        $dv = function (array $v, array $pesos) { $s = 0; foreach ($pesos as $i => $p) { $s += $v[$i] * $p; } return $s % 11 < 2 ? 0 : 11 - $s % 11; };
        $d1 = $dv($v, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $d2 = $dv([...$v, $d1], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $this->assertTrue(\App\Rules\Cnpj::valido($base.$d1.$d2));
        $this->assertFalse(\App\Rules\Cnpj::valido($base.$d1.(($d2 + 1) % 10)));
    }

    public function test_cep_e_consultado_no_viacep_pelo_servidor(): void
    {
        Http::fake([
            'viacep.com.br/ws/78450000/json/' => Http::response(['cep' => '78450-000', 'logradouro' => '', 'bairro' => '', 'localidade' => 'Nova Mutum', 'uf' => 'MT']),
            'viacep.com.br/ws/01001000/json/' => Http::response(['logradouro' => 'Praça da Sé', 'bairro' => 'Sé', 'localidade' => 'São Paulo', 'uf' => 'SP']),
            'viacep.com.br/ws/99999999/json/' => Http::response(['erro' => true]),
        ]);
        $this->getJson('/cep/78450-000')->assertOk()->assertJson(['encontrado' => true, 'cidade' => 'Nova Mutum', 'uf' => 'MT']);
        $this->getJson('/cep/01001000')->assertOk()->assertJson(['encontrado' => true, 'logradouro' => 'Praça da Sé', 'bairro' => 'Sé']);
        $this->getJson('/cep/99999999')->assertOk()->assertJson(['encontrado' => false]);
        $this->getJson('/cep/1234')->assertNotFound();
    }
}
