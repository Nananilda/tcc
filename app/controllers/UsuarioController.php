<?php
/**
 * app/controllers/UsuarioController.php
 * Controller: UsuarioController
 * Orquestra o cadastro, a edição e a exclusão de contas de usuário. As regras
 * (validações, proteção do último administrador, hash da senha) ficam no
 * UsuarioModel; este controller aplica as permissões e registra os logs.
 *
 * É usado pelas telas web (app/views/usuarios/cadastro|editar|excluir.php) e
 * pela API (api/usuarios.php), de modo que as duas portas de entrada
 * obedeçam exatamente às mesmas regras.
 *
 * TODAS as operações exigem administrador.
 */

require_once __DIR__ . '/../models/UsuarioModel.php';

class UsuarioController
{
    private UsuarioModel $model;
    private PDO $pdo;
    private bool $ehAdmin;
    private ?int $usuarioLogadoId;

    /**
     * @param PDO      $pdo             conexão com o banco
     * @param bool     $ehAdmin         o usuário logado é administrador?
     * @param int|null $usuarioLogadoId ID do usuário logado (para logs e para
     *                                  impedir que ele exclua a própria conta)
     */
    public function __construct(PDO $pdo, bool $ehAdmin, ?int $usuarioLogadoId)
    {
        $this->pdo = $pdo;
        $this->model = new UsuarioModel($pdo);
        $this->ehAdmin = $ehAdmin;
        $this->usuarioLogadoId = $usuarioLogadoId;
    }

    // ── Consultas ────────────────────────────────────────────────────────────

    /** Lista todos os usuários (sem senha). */
    public function listar(): array
    {
        return $this->model->listarTodos();
    }

    /** Busca um usuário pelo ID. Devolve false se não existir. */
    public function buscarPorId(int $id): array|false
    {
        return $this->model->buscarPorId($id);
    }

    /** Busca um usuário pelo login. Devolve false se não existir. */
    public function buscarPorLogin(string $login): array|false
    {
        return $this->model->buscarPorLogin(trim($login));
    }

    // ── Ações ────────────────────────────────────────────────────────────────
    // Todas devolvem ['mensagem' => string, 'erros' => string[]] (e, no
    // cadastro, também 'id' com o código do novo usuário).

    /**
     * Cadastra um novo usuário.
     *
     * @param array $dados nome, login, senha, confirmar, tipo, status
     */
    public function cadastrar(array $dados): array
    {
        if (!$this->ehAdmin) {
            return $this->resposta('', ['Acesso negado.']);
        }

        $nome = trim($dados['nome'] ?? '');
        $login = trim($dados['login'] ?? '');
        $senha = (string) ($dados['senha'] ?? '');
        $confirmar = (string) ($dados['confirmar'] ?? '');
        $tipo = (string) ($dados['tipo'] ?? 'usuario');
        $status = (string) ($dados['status'] ?? 'ativo');

        $erros = $this->model->validarCadastro($nome, $login, $senha, $confirmar, $tipo, $status);
        if (!empty($erros)) {
            return $this->resposta('', $erros);
        }

        try {
            $novoId = $this->model->criar($nome, $login, $senha, $tipo, $status);
            $this->registrarLog('CADASTRO_USUARIO', "Novo usuário: $login (ID $novoId) - Tipo: $tipo");
            $resposta = $this->resposta("Usuário \"$login\" cadastrado com sucesso.", []);
            $resposta['id'] = $novoId;
            return $resposta;
        } catch (PDOException $e) {
            return $this->resposta('', [$this->erroDeBanco('cadastrar o usuário', $e)]);
        }
    }

    /**
     * Atualiza os dados de um usuário (a senha só muda se uma nova for enviada).
     *
     * @param array $dados id, nome, login, tipo, status, senha?, confirmar_senha?
     */
    public function atualizar(array $dados): array
    {
        if (!$this->ehAdmin) {
            return $this->resposta('', ['Acesso negado.']);
        }

        $id = (int) ($dados['id'] ?? 0);
        $nome = trim($dados['nome'] ?? '');
        $login = trim($dados['login'] ?? '');
        $tipo = (string) ($dados['tipo'] ?? 'usuario');
        $status = (string) ($dados['status'] ?? 'ativo');
        $senha = (string) ($dados['senha'] ?? '');
        $confirmar = (string) ($dados['confirmar_senha'] ?? '');

        $erros = $this->model->validarEdicao($id, $nome, $login, $tipo, $status, $senha, $confirmar);
        if (!empty($erros)) {
            return $this->resposta('', $erros);
        }

        try {
            $this->model->atualizar($id, $nome, $login, $tipo, $status, $senha !== '' ? $senha : null);
            $this->registrarLog('EDICAO_USUARIO', "Usuário ID $id atualizado: $login");
            return $this->resposta('Usuário atualizado com sucesso.', []);
        } catch (PDOException $e) {
            return $this->resposta('', [$this->erroDeBanco('atualizar o usuário', $e)]);
        }
    }

    /**
     * Exclui um usuário. Não permite excluir a própria conta.
     */
    public function excluir(int $id): array
    {
        if (!$this->ehAdmin) {
            return $this->resposta('', ['Acesso negado.']);
        }
        if ($id <= 0) {
            return $this->resposta('', ['ID do usuário inválido.']);
        }
        if ($id === $this->usuarioLogadoId) {
            return $this->resposta('', ['Você não pode excluir seu próprio usuário.']);
        }

        try {
            if (!$this->model->excluir($id)) {
                return $this->resposta('', ['Usuário não encontrado para exclusão.']);
            }
            $this->registrarLog('EXCLUSAO_USUARIO', "Usuário ID $id excluído");
            return $this->resposta('Usuário excluído com sucesso.', []);
        } catch (PDOException $e) {
            return $this->resposta('', [$this->erroDeBanco('excluir o usuário', $e)]);
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Monta o formato padrão de resposta. */
    private function resposta(string $mensagem, array $erros): array
    {
        return ['mensagem' => $mensagem, 'erros' => $erros];
    }

    private function registrarLog(string $acao, string $detalhe): void
    {
        if (function_exists('registrarLog')) {
            registrarLog($this->pdo, $this->usuarioLogadoId, $acao, $detalhe);
        }
    }

    /**
     * Grava o erro técnico no log do servidor e devolve uma mensagem genérica
     * para o usuário (não expor detalhes do SQL na tela).
     */
    private function erroDeBanco(string $acao, PDOException $e): string
    {
        error_log('[' . APP_NOME . '] Erro ao ' . $acao . ': ' . $e->getMessage());
        return "Não foi possível $acao. Tente novamente.";
    }
}
