<?php

namespace Tests\Feature;

use App\Models\AtestadoFilho;
use App\Models\AtestadoPedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private const SENHA = 'senha-de-teste-123';

    private function admin(): User
    {
        return User::create(['name' => 'Administrador', 'email' => 'admin@teste.local', 'password' => self::SENHA]);
    }

    private function pedido(string $cpf, string $unidade, int $unidadeId, string $filho, string $status = 'aguardando', string $protocolo = 'ATV000001', string $vinculo = 'mae'): AtestadoFilho
    {
        $p = AtestadoPedido::create([
            'uuid' => (string) Str::uuid(), 'responsavel_nome' => 'Maria Responsável', 'vinculo' => $vinculo, 'cpf' => $cpf,
            'cpf_hash' => AtestadoPedido::hashCpf($cpf), 'telefone' => '(65) 99999-1234', 'cep' => '78450-000', 'email' => 'maria@exemplo.com',
            'data_nascimento_responsavel' => '1990-05-10', 'unidade_id' => $unidadeId, 'unidade_nome' => $unidade, 'acs_nome' => 'Joana ACS',
        ]);

        return $p->filhos()->create(['nome' => $filho, 'serie' => '3º ano', 'data_nascimento' => '2017-03-01', 'nome_mae' => 'Maria Responsável',
            'protocolo' => $protocolo, 'status' => $status, 'token' => Str::random(48)]);
    }

    public function test_tela_inicial_tem_botao_discreto_de_acesso_adm(): void
    {
        $this->get('/')->assertOk()->assertSee('Acesso Adm')->assertSee(route('admin.login'), false);
        $this->get('/admin/login')->assertOk()->assertSee('Acesso administrativo');
    }

    public function test_visitante_e_mandado_para_o_login_em_todas_as_rotas_administrativas(): void
    {
        foreach (['/admin', '/admin/pedidos', '/admin/pedidos/1', '/admin/relatorios/pedidos.pdf', '/admin/relatorios/resumo.pdf'] as $rota) {
            $this->get($rota)->assertRedirect(route('admin.login'));
        }
        $this->post('/admin/sair')->assertRedirect(route('admin.login'));
    }

    public function test_login_com_senha_certa_entra_e_senha_errada_ou_email_desconhecido_recebe_a_mesma_mensagem(): void
    {
        $this->admin();

        $this->post('/admin/login', ['email' => 'admin@teste.local', 'password' => 'errada'])
            ->assertSessionHasErrors(['email' => 'E-mail ou senha incorretos.']);
        $this->post('/admin/login', ['email' => 'ninguem@teste.local', 'password' => self::SENHA])
            ->assertSessionHasErrors(['email' => 'E-mail ou senha incorretos.']);
        $this->assertGuest();

        // e-mail não diferencia maiúsculas de minúsculas
        $this->post('/admin/login', ['email' => ' ADMIN@Teste.Local ', 'password' => self::SENHA])->assertRedirect(route('admin.pedidos'));
        $this->assertAuthenticated();
        $this->get('/admin/pedidos')->assertOk();
        $this->assertStringContainsString('no-store', (string) $this->get('/admin/pedidos')->headers->get('Cache-Control'));
    }

    public function test_apos_cinco_erros_o_login_e_bloqueado_mesmo_com_a_senha_certa(): void
    {
        $this->admin();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => 'admin@teste.local', 'password' => 'errada']);
        }
        $this->post('/admin/login', ['email' => 'admin@teste.local', 'password' => self::SENHA])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString('Muitas tentativas', session('errors')->first('email'));
    }

    public function test_logout_encerra_a_sessao(): void
    {
        $this->actingAs($this->admin())->post('/admin/sair')->assertRedirect(route('inicio'));
        $this->assertGuest();
    }

    public function test_lista_mostra_pedidos_com_cpf_mascarado_e_filtra(): void
    {
        $this->pedido('52998224725', 'PSF PARQUE DO SOL', 8, 'João da Silva', 'aguardando', 'ATV000001');
        $this->pedido('11144477735', 'ESF ALTO DA COLINA', 4, 'Ana Souza', 'baixado', 'ATV000002', 'pai');
        $this->pedido('39053344705', 'ESF ALTO DA COLINA', 4, 'Pedro Lima', 'cancelado', 'ATV000003', 'outro');
        $admin = $this->admin();

        $r = $this->actingAs($admin)->get('/admin/pedidos')->assertOk()
            ->assertSee('ATV000001')->assertSee('ATV000002')->assertSee('ATV000003')
            ->assertSee('***.982.247-**')->assertDontSee('529.982.247-25')->assertDontSee('52998224725');
        $this->assertSame(3, (int) $r->viewData('filhos')->total());

        $this->actingAs($admin)->get('/admin/pedidos?unidade=4')->assertSee('Ana Souza')->assertSee('Pedro Lima')->assertDontSee('João da Silva');
        $this->actingAs($admin)->get('/admin/pedidos?status=cancelado')->assertSee('ATV000003')->assertDontSee('ATV000001');
        $this->actingAs($admin)->get('/admin/pedidos?vinculo=pai')->assertSee('ATV000002')->assertDontSee('ATV000001');
        $this->actingAs($admin)->get('/admin/pedidos?q=atv000001')->assertSee('ATV000001')->assertDontSee('ATV000002');
        $this->actingAs($admin)->get('/admin/pedidos?q=ana+souza')->assertSee('ATV000002')->assertDontSee('ATV000001');
        // busca por CPF completo (via hash, sem expor o CPF no banco)
        $this->actingAs($admin)->get('/admin/pedidos?q=111.444.777-35')->assertSee('ATV000002')->assertDontSee('ATV000001');
        $this->actingAs($admin)->get('/admin/pedidos?de='.now()->addDay()->format('Y-m-d'))->assertSee('Nenhum pedido encontrado');
        // lixo nos filtros é ignorado (não quebra)
        $this->actingAs($admin)->get('/admin/pedidos?status=hack&unidade=x&de=amanha')->assertOk()->assertSee('ATV000001');
    }

    public function test_detalhe_mostra_dados_completos_do_responsavel(): void
    {
        $f = $this->pedido('52998224725', 'PSF PARQUE DO SOL', 8, 'João da Silva');
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/pedidos/'.$f->id)->assertOk()
            ->assertSee('529.982.247-25')->assertSee('(65) 99999-1234')->assertSee('maria@exemplo.com')
            ->assertSee('Joana ACS')->assertSee('10/05/1990')->assertSee('Mãe');
        $this->actingAs($admin)->get('/admin/pedidos/99999')->assertNotFound();
    }

    public function test_relatorios_em_pdf_respeitam_os_filtros(): void
    {
        $this->pedido('52998224725', 'PSF PARQUE DO SOL', 8, 'João da Silva', 'aguardando', 'ATV000001');
        $this->pedido('11144477735', 'ESF ALTO DA COLINA', 4, 'Ana Souza', 'baixado', 'ATV000002');
        $admin = $this->admin();

        foreach (['/admin/relatorios/pedidos.pdf', '/admin/relatorios/pedidos.pdf?unidade=4&status=baixado', '/admin/relatorios/resumo.pdf', '/admin/relatorios/resumo.pdf?de=2000-01-01'] as $url) {
            $r = $this->actingAs($admin)->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $r->getContent());
            $this->assertStringContainsString('attachment', $r->headers->get('Content-Disposition'));
        }
        // PDF sem resultados também gera (mensagem de vazio)
        $this->actingAs($admin)->get('/admin/relatorios/pedidos.pdf?status=cancelado')->assertOk();
    }

    public function test_comando_cria_acesso_com_senha_guardada_apenas_como_hash(): void
    {
        $this->artisan('admin:criar', ['email' => 'Chefe@Exemplo.com', '--nome' => 'Chefe'])
            ->expectsQuestion('Senha de acesso', 'uma-senha-forte-1')
            ->expectsQuestion('Repita a senha', 'uma-senha-forte-1')
            ->assertSuccessful();

        $u = User::where('email', 'chefe@exemplo.com')->firstOrFail();
        $this->assertNotSame('uma-senha-forte-1', $u->password);
        $this->assertTrue(Hash::check('uma-senha-forte-1', $u->password));

        // senhas diferentes ou curtas não alteram nada
        $this->artisan('admin:criar', ['email' => 'chefe@exemplo.com'])
            ->expectsQuestion('Senha de acesso', 'outra-senha-1')->expectsQuestion('Repita a senha', 'diferente-1')->assertFailed();
        $this->artisan('admin:criar', ['email' => 'chefe@exemplo.com'])
            ->expectsQuestion('Senha de acesso', 'curta')->expectsQuestion('Repita a senha', 'curta')->assertFailed();
        $this->assertTrue(Hash::check('uma-senha-forte-1', $u->fresh()->password));
    }
}
