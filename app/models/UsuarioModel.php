<?php
/**
 * app/models/UsuarioModel.php
 * Model: UsuarioModel
 * Regras de negócio e acesso a dados da tabela `usuario` (contas do sistema).
 *
 * É usado tanto pelas telas web (app/views/usuarios/*.php, via
 * UsuarioController) quanto pela API (api/usuarios.php). O hash da senha
 * NUNCA é devolvido pelos métodos de consulta.
 */

require_once __DIR__ . '/../../includes/auth.php'; // validarLogin() e validarSenha()

class UsuarioModel
{
    /** Tipos de conta aceitos (valores do ENUM `usuario.tipo`). */
    public const TIPOS = ['usuario', 'admin'];

    /** Situações de conta aceitas (valores do ENUM `usuario.status`). */
    public const STATUS = ['ativo', 'inativo'];

    /** Colunas públicas (tudo, menos o hash da senha). */
    private const COLUNAS = 'id, nome, login, tipo, status, criado_em';

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ── Consulta ─────────────────────────────────────────────────────────────

    /**
     * Lista todos os usuários (sem a senha), em ordem alfabética.
     */
    public function listarTodos(): array
    {
        $stmt = $this->pdo->query("SELECT " . self::COLUNAS . " FROM usuario ORDER BY nome ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca um usuário pelo ID. Devolve false se não existir.
     */
    public function buscarPorId(int $id): array|false
    {
        $stmt = $this->pdo->prepare("SELECT " . self::COLUNAS . " FROM usuario WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Busca um usuário pelo login (comparação exata). Devolve false se não existir.
     */
    public function buscarPorLogin(string $login): array|false
    {
        $stmt = $this->pdo->prepare("SELECT " . self::COLUNAS . " FROM usuario WHERE login = :login");
        $stmt->execute([':login' => $login]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica se um login já está em uso.
     *
     * @param int|null $ignorarId ID de um usuário a desconsiderar (útil na edição,
     *                            para o usuário não "conflitar" com ele mesmo).
     */
    public function loginExiste(string $login, ?int $ignorarId = null): bool
    {
        if ($ignorarId === null) {
            $stmt = $this->pdo->prepare("SELECT id FROM usuario WHERE login = :login");
            $stmt->execute([':login' => $login]);
        } else {
            $stmt = $this->pdo->prepare("SELECT id FROM usuario WHERE login = :login AND id <> :id");
            $stmt->execute([':login' => $login, ':id' => $ignorarId]);
        }
        return (bool) $stmt->fetch();
    }

    /**
     * Quantos administradores ATIVOS existem (opcionalmente ignorando um deles).
     * Usado para impedir que o sistema fique sem nenhum administrador.
     */
    public function contarAdminsAtivos(?int $ignorarId = null): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM usuario
            WHERE tipo = 'admin' AND status = 'ativo' AND id <> :id
        ");
        $stmt->execute([':id' => $ignorarId ?? 0]);
        return (int) $stmt->fetchColumn();
    }

    // ── Escrita ──────────────────────────────────────────────────────────────

    /**
     * Insere um novo usuário (senha gravada como hash bcrypt) e retorna o ID gerado.
     */
    public function criar(string $nome, string $login, string $senha, string $tipo, string $status): int
    {
        $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $this->pdo->prepare("
            INSERT INTO usuario (nome, login, senha, tipo, status, criado_em)
            VALUES (:nome, :login, :senha, :tipo, :status, NOW())
        ");
        $stmt->execute([
            ':nome' => $nome,
            ':login' => $login,
            ':senha' => $hash,
            ':tipo' => $tipo,
            ':status' => $status,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Atualiza os dados de um usuário. A senha só é alterada se $novaSenha for
     * informada (string não vazia); caso contrário, a senha atual é mantida.
     */
    public function atualizar(
        int $id,
        string $nome,
        string $login,
        string $tipo,
        string $status,
        ?string $novaSenha = null
    ): bool {
        $params = [
            ':nome' => $nome,
            ':login' => $login,
            ':tipo' => $tipo,
            ':status' => $status,
            ':id' => $id,
        ];

        $trocaSenha = $novaSenha !== null && $novaSenha !== '';
        if ($trocaSenha) {
            $params[':senha'] = password_hash($novaSenha, PASSWORD_BCRYPT, ['cost' => 12]);
        }

        $stmt = $this->pdo->prepare("
            UPDATE usuario
            SET nome = :nome,
                login = :login,
                tipo = :tipo,
                status = :status" . ($trocaSenha ? ",
                senha = :senha" : "") . "
            WHERE id = :id
        ");
        return $stmt->execute($params);
    }

    /**
     * Exclui um usuário pelo ID. Devolve true se havia o que excluir.
     * (Alertas resolvidos por ele permanecem, com `resolvido_por` = NULL.)
     */
    public function excluir(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM usuario WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // ── Validações ───────────────────────────────────────────────────────────
    // Todos devolvem uma lista de mensagens de erro (vazia = dado válido).

    /** Nome com 3 a 100 caracteres, só letras, espaços, hífen e ponto. */
    public function validarNome(string $nome): array
    {
        $erros = [];
        $tamanho = function_exists('mb_strlen') ? mb_strlen($nome, 'UTF-8') : strlen($nome);

        if ($tamanho < 3) {
            $erros[] = 'Nome deve ter no mínimo 3 caracteres.';
        }
        if ($tamanho > 100) {
            $erros[] = 'Nome muito longo (máx 100 chars).';
        }
        if ($nome !== '' && !preg_match('/^[\p{L}\s\-\.]+$/u', $nome)) {
            $erros[] = 'Nome contém caracteres inválidos.';
        }
        return $erros;
    }

    public function validarTipo(string $tipo): array
    {
        return in_array($tipo, self::TIPOS, true) ? [] : ['Tipo de usuário inválido.'];
    }

    public function validarStatus(string $status): array
    {
        return in_array($status, self::STATUS, true) ? [] : ['Status inválido.'];
    }

    /**
     * Valida todos os campos de um NOVO cadastro (inclusive login duplicado).
     */
    public function validarCadastro(
        string $nome,
        string $login,
        string $senha,
        string $confirmar,
        string $tipo,
        string $status
    ): array {
        $erros = $this->validarNome($nome);

        if (!validarLogin($login)) {
            $erros[] = 'Login inválido (3-50 chars, apenas letras, números, ponto, hífen, underscore).';
        }

        $erros = array_merge($erros, $this->validarTipo($tipo), $this->validarStatus($status), validarSenha($senha));

        if ($senha !== $confirmar) {
            $erros[] = 'As senhas não coincidem.';
        }

        // Só consulta o banco se o restante já está correto
        if (empty($erros) && $this->loginExiste($login)) {
            $erros[] = "O login '$login' já está em uso.";
        }
        return $erros;
    }

    /**
     * Valida a EDIÇÃO de um usuário existente. A senha é opcional: só é
     * validada quando o administrador digita uma nova.
     */
    public function validarEdicao(
        int $id,
        string $nome,
        string $login,
        string $tipo,
        string $status,
        string $senha = '',
        string $confirmar = ''
    ): array {
        $usuarioAtual = $id > 0 ? $this->buscarPorId($id) : false;
        if (!$usuarioAtual) {
            return ['Usuário não encontrado.'];
        }

        $erros = $this->validarNome($nome);

        if (!validarLogin($login)) {
            $erros[] = 'Login inválido (3-50 chars, apenas letras, números, ponto, hífen, underscore).';
        }

        $erros = array_merge($erros, $this->validarTipo($tipo), $this->validarStatus($status));

        if ($senha !== '' || $confirmar !== '') {
            $erros = array_merge($erros, validarSenha($senha));
            if ($senha !== $confirmar) {
                $erros[] = 'As senhas não coincidem.';
            }
        }

        if (empty($erros) && $this->loginExiste($login, $id)) {
            $erros[] = "O login '$login' já está em uso.";
        }

        // Proteção contra "trancar o sistema": o último administrador ativo
        // não pode ser rebaixado nem inativado.
        $eraAdminAtivo = $usuarioAtual['tipo'] === 'admin' && $usuarioAtual['status'] === 'ativo';
        $continuaAdminAtivo = $tipo === 'admin' && $status === 'ativo';
        if (empty($erros) && $eraAdminAtivo && !$continuaAdminAtivo && $this->contarAdminsAtivos($id) === 0) {
            $erros[] = 'Não é possível rebaixar ou inativar o único administrador ativo.';
        }
        return $erros;
    }
}
