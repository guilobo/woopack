<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\IntegrationController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\MetaAuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\WhatsAppController;
use Illuminate\Support\Facades\Route;

Route::view('/politica-de-privacidade', 'legal', [
    'eyebrow' => 'Privacidade',
    'title' => 'Politica de Privacidade',
    'intro' => 'Esta pagina explica como o WooPack coleta, utiliza e protege dados relacionados ao uso da plataforma e das integracoes conectadas pelo usuario.',
    'sections' => [
        [
            'title' => 'Dados coletados',
            'paragraphs' => [
                'Coletamos dados de cadastro, configuracoes de integracao e informacoes operacionais necessarias para oferecer o painel logístico do WooPack.',
            ],
            'items' => [
                'nome, e-mail e credenciais de acesso da conta',
                'configuracoes de conexao com WooCommerce e futuras integracoes autorizadas pelo usuario',
                'dados operacionais de pedidos, embalagem e historico de uso da plataforma',
            ],
        ],
        [
            'title' => 'Como usamos os dados',
            'paragraphs' => [
                'Os dados sao utilizados para autenticar usuarios, operar o painel, sincronizar informacoes com servicos autorizados e oferecer suporte tecnico.',
                'Nao vendemos dados pessoais. O acesso interno e limitado ao necessario para manutencao, seguranca e suporte da plataforma.',
            ],
        ],
        [
            'title' => 'Compartilhamento e seguranca',
            'paragraphs' => [
                'Os dados podem ser compartilhados apenas com provedores necessarios para a operacao das integracoes solicitadas pelo proprio usuario, como WooCommerce, Meta e provedores de infraestrutura.',
                'Adotamos medidas tecnicas razoaveis para proteger acessos, segredos de integracao e dados da conta.',
            ],
        ],
    ],
    'calloutTitle' => 'Solicitacoes sobre privacidade',
    'calloutBody' => 'Para esclarecer duvidas, atualizar informacoes ou registrar uma solicitacao ligada a privacidade, envie um e-mail para nosso canal oficial de suporte.',
])->name('legal.privacy');

Route::view('/termos-de-servico', 'legal', [
    'eyebrow' => 'Termos',
    'title' => 'Termos de Servico',
    'intro' => 'Ao utilizar o WooPack, o usuario concorda com estas regras de uso, que definem responsabilidades, limites e boas praticas para utilizacao da plataforma.',
    'sections' => [
        [
            'title' => 'Uso permitido',
            'paragraphs' => [
                'O WooPack destina-se ao gerenciamento logístico, acompanhamento de pedidos e uso de integracoes empresariais conectadas pelo proprio usuario.',
            ],
            'items' => [
                'o usuario deve fornecer dados verdadeiros no cadastro',
                'o usuario e responsavel pelas credenciais e acessos conectados na propria conta',
                'o usuario deve respeitar as politicas dos servicos integrados, incluindo WooCommerce, Meta e WhatsApp',
            ],
        ],
        [
            'title' => 'Responsabilidades',
            'paragraphs' => [
                'Cada conta e responsavel pelas mensagens, dados e operacoes realizadas por meio da plataforma e das integracoes vinculadas.',
                'O WooPack pode suspender acessos em caso de uso indevido, tentativa de fraude, violacao legal ou comprometimento de seguranca.',
            ],
        ],
        [
            'title' => 'Disponibilidade e alteracoes',
            'paragraphs' => [
                'A plataforma pode ser atualizada, aprimorada ou ajustada a qualquer momento para manutencao, seguranca e evolucao do produto.',
                'Sempre que necessario, estes termos podem ser revisados para refletir mudancas legais, tecnicas ou operacionais.',
            ],
        ],
    ],
    'calloutTitle' => 'Suporte contratual e operacional',
    'calloutBody' => 'Em caso de duvidas sobre uso permitido, responsabilidades ou necessidade de suporte, entre em contato conosco pelo e-mail oficial da plataforma.',
])->name('legal.terms');

Route::view('/exclusao-de-dados', 'legal', [
    'eyebrow' => 'Dados',
    'title' => 'Exclusao de Dados do Usuario',
    'intro' => 'Usuarios podem solicitar a exclusao de dados pessoais e dados operacionais associados a conta, observadas obrigacoes legais e tecnicas de retencao minima.',
    'sections' => [
        [
            'title' => 'Como solicitar',
            'paragraphs' => [
                'Envie uma solicitacao para nosso canal de suporte informando o e-mail da conta e, se possivel, o identificador da integracao relacionada.',
            ],
            'items' => [
                'informe o e-mail da conta cadastrada no WooPack',
                'descreva se a exclusao deve abranger apenas uma integracao ou a conta completa',
                'aguarde a confirmacao do atendimento antes de remover acessos externos',
            ],
        ],
        [
            'title' => 'O que e removido',
            'paragraphs' => [
                'Quando aplicavel, removeremos dados de cadastro, credenciais salvas, configuracoes de integracao e dados internos operacionais vinculados ao usuario.',
            ],
        ],
        [
            'title' => 'Prazo de atendimento',
            'paragraphs' => [
                'As solicitacoes sao analisadas e atendidas em prazo razoavel, respeitando confirmacao de identidade, obrigacoes legais e limites tecnicos de auditoria e seguranca.',
            ],
        ],
    ],
    'calloutTitle' => 'Canal oficial para exclusao',
    'calloutBody' => 'Para abrir uma solicitacao de exclusao de dados, envie um e-mail com o assunto "Exclusao de dados WooPack" para o canal oficial abaixo.',
])->name('legal.data-deletion');

Route::prefix('api')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/auth/check', [AuthController::class, 'check']);
    Route::get('/me', [AuthController::class, 'me'])->middleware('woopack.auth');
    Route::get('/invitations/{token}', [InvitationController::class, 'show']);
    Route::post('/invitations/accept', [InvitationController::class, 'accept']);

    Route::middleware('woopack.auth')->group(function (): void {
        Route::get('/integration', [IntegrationController::class, 'show']);
        Route::put('/integration', [IntegrationController::class, 'update']);
        Route::post('/integration/test', [IntegrationController::class, 'test']);
        Route::get('/meta/connect/config', [MetaAuthController::class, 'config']);
        Route::get('/meta/connect/status', [MetaAuthController::class, 'status']);
        Route::delete('/meta/connect/status', [MetaAuthController::class, 'clearStatus']);
        Route::get('/whatsapp', [WhatsAppController::class, 'show']);
        Route::post('/whatsapp/connect', [WhatsAppController::class, 'connect']);
        Route::post('/whatsapp/test', [WhatsAppController::class, 'test']);
        Route::delete('/whatsapp', [WhatsAppController::class, 'disconnect']);
        Route::post('/invitations', [InvitationController::class, 'store'])->middleware('woopack.admin');
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{id}', [OrderController::class, 'show'])->whereNumber('id');
        Route::post('/orders/{id}/pack', [OrderController::class, 'pack'])->whereNumber('id');
        Route::get('/stats', [OrderController::class, 'stats']);
    });
});

Route::get('/auth/meta/callback', [MetaAuthController::class, 'callback'])->name('meta.callback');

Route::view('/{any?}', 'app')
    ->where('any', '.*')
    ->name('login');
