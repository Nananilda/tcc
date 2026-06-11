<?php
/**
 * Usuario.php
 * Model — regras de negócio e acesso a dados do usuário
 */

class Usuario
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Verifica se um login já está em uso.
     */
    public function loginExiste(string $login): bool
    {
        $chk = $this->pdo->prepare("SELECT id FROM usuario WHERE login = ?");
        $chk->execute([$login]);
        return (bool) $chk->fetch();
    }

    /**
     * Insere um novo usuário e retorna o ID gerado.
     */
    public function criar(string $nome, string $login, string $senha, string $tipo, string $status): int
    {
        $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $this->pdo->prepare("
            INSERT INTO usuario (nome, login, senha, tipo, status, criado_em)
            VALUES (:nome, :login, :senha, :tipo, :status, NOW())
        ");
        $stmt->execute([
            ':nome'   => $nome,
            ':login'  => $login,
            ':senha'  => $hash,
            ':tipo'   => $tipo,
            ':status' => $status,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    // -------------------------------------------------------------------------
    // Validações (extraídas do cadastro.php original)
    // -------------------------------------------------------------------------

    public function validarNome(string $nome): array
    {
        $erros = [];
        if (empty($nome) || strlen($nome) < 3) {
            $erros[] = 'Nome deve ter no mínimo 3 caracteres.';
        }
        if (strlen($nome) > 100) {
            $erros[] = 'Nome muito longo (máx 100 chars).';
        }
        if (!preg_match('/^[\p{L}\s\-\.]+$/u', $nome)) {
            $erros[] = 'Nome contém caracteres inválidos.';
        }
        return $erros;
    }

    public function validarTipo(string $tipo): array
    {
        if (!in_array($tipo, ['admin', 'usuario'])) {
            return ['Tipo de usuário inválido.'];
        }
        return [];
    }

    public function validarStatus(string $status): array
    {
        if (!in_array($status, ['ativo', 'inativo'])) {
            return ['Status inválido.'];
        }
        return [];
    }
}