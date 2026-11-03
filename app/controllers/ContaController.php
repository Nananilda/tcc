<?php
require_once __DIR__.'/../models/UsuarioModel.php';
/** A identidade vem exclusivamente da sessão, nunca de um ID enviado pelo cliente. */
final class ContaController
{
    public function __construct(private PDO $pdo, private int $usuarioId) {}
    public function consultar(): array { return (new UsuarioModel($this->pdo))->buscarPorId($this->usuarioId) ?: []; }
    public function salvar(array $dados, array $arquivos=[]): array
    {
        $erros=[];
        foreach (['nome','senha_atual','senha','confirmar_senha','tema','acao','remover_foto'] as $c) {
            if (isset($dados[$c]) && !is_string($dados[$c])) return ['erros'=>['Campo inválido: '.$c], 'mensagem'=>''];
        }
        if (array_intersect(['id','usuario_id','tipo','status','estoque_ids','administrador_principal'],array_keys($dados))) return ['erros'=>['As permissões da conta não podem ser alteradas nesta área.'],'mensagem'=>''];
        $u=$this->consultar();
        $tema=$dados['tema']??$u['tema'];
        if (!in_array($tema,['claro','escuro','automatico'],true)) $erros['tema']='Selecione um tema válido.';
        if (($dados['acao']??'')==='tema') {
            if (!$erros) $this->pdo->prepare('UPDATE usuario SET tema=? WHERE id=?')->execute([$tema,$this->usuarioId]);
            return ['erros'=>$erros,'mensagem'=>$erros?'':'Tema salvo para sua conta.'];
        }
        $nome=trim($dados['nome']??'');
        $validacao=(new UsuarioModel($this->pdo))->validarNome($nome);
        if ($validacao) $erros['nome']=implode(' ',$validacao);
        $senha=$dados['senha']??'';$hash=null;
        if ($senha!=='' || ($dados['confirmar_senha']??'')!=='') {
            $q=$this->pdo->prepare('SELECT senha FROM usuario WHERE id=?');$q->execute([$this->usuarioId]);
            if (!password_verify($dados['senha_atual']??'', $q->fetchColumn())) $erros['senha_atual']='A senha atual não confere.';
            $regras=validarSenha($senha);
            if ($regras) $erros['senha']=implode(' ',$regras);
            if ($senha!==($dados['confirmar_senha']??'')) $erros['confirmar_senha']='As senhas não coincidem.';
            if (strlen($senha)>72) $erros['senha']='Use até 72 bytes na senha.';
            if (!$erros) $hash=password_hash($senha,PASSWORD_BCRYPT,['cost'=>12]);
        }
        $foto=$u['foto'];
        if (($dados['remover_foto']??'')==='1') $foto=null;
        $arquivo=$arquivos['foto']??null;
        if ($arquivo && ($arquivo['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) {
            try {
                if (($arquivo['error']??null)!==UPLOAD_ERR_OK || !is_int($arquivo['size']??null) || $arquivo['size']>2097152 || !is_string($arquivo['tmp_name']??null) || !is_uploaded_file($arquivo['tmp_name'])) {
                    throw new InvalidArgumentException('Envie uma imagem JPG ou PNG de até 2 MB.');
                }
                $conteudo=@file_get_contents($arquivo['tmp_name'],false,null,0,2097153);
                if ($conteudo===false || $conteudo==='' || strlen($conteudo)>2097152) {
                    throw new InvalidArgumentException('Envie uma imagem JPG ou PNG de até 2 MB.');
                }
                // A identificação usa os bytes recebidos, não o nome/MIME enviado pelo navegador.
                // getimagesizefromstring faz parte do PHP padrão e não depende da extensão GD.
                $info=@getimagesizefromstring($conteudo);
                if (!$info || !in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG],true) || $info[0]<1 || $info[1]<1 || $info[0]*$info[1]>16000000) {
                    throw new InvalidArgumentException('Imagem inválida. Use JPG ou PNG com até 16 megapixels.');
                }
                $mime=$info[2]===IMAGETYPE_PNG?'image/png':'image/jpeg';

                // GD é uma otimização opcional. Sem ela, mantém a imagem validada no banco,
                // como URL de dados; nenhum arquivo enviado é gravado/executado na pasta do site.
                if (function_exists('imagecreatefromstring') && function_exists('imagescale') && function_exists('imagepng')) {
                    $orig=@imagecreatefromstring($conteudo);
                    if (!$orig) throw new InvalidArgumentException('Não foi possível ler a imagem.');
                    $img=false;
                    try {
                        $escala=min(1,512/max($info[0],$info[1]));
                        $img=@imagescale($orig,max(1,(int)($info[0]*$escala)),max(1,(int)($info[1]*$escala)));
                        if (!$img) throw new InvalidArgumentException('Não foi possível processar a imagem.');
                        imagesavealpha($img,true);
                        ob_start();
                        try {
                            $gravou=imagepng($img);
                            $png=ob_get_contents();
                        } finally {
                            ob_end_clean();
                        }
                        if (!$gravou || $png===false || $png==='') throw new InvalidArgumentException('Não foi possível processar a imagem.');
                        $conteudo=$png;
                        $mime='image/png';
                    } finally {
                        imagedestroy($orig);
                        if ($img) imagedestroy($img);
                    }
                }
                $foto='data:'.$mime.';base64,'.base64_encode($conteudo);
            } catch (InvalidArgumentException $e) { $erros['foto']=$e->getMessage(); }
        }
        if ($erros) return ['erros'=>$erros,'mensagem'=>''];
        $sql='UPDATE usuario SET nome=?,tema=?,foto=?'.($hash?',senha=?':'').' WHERE id=?';
        $params=[$nome,$tema,$foto];if ($hash) $params[]=$hash;$params[]=$this->usuarioId;
        $this->pdo->prepare($sql)->execute($params);
        registrarLog($this->pdo,$this->usuarioId,'CONTA_ATUALIZADA','Dados pessoais atualizados.');
        $_SESSION['usuario_nome']=$nome;$_SESSION['usuario_foto']=$foto;$_SESSION['usuario_tema']=$tema;
        return ['erros'=>[],'mensagem'=>'Sua conta foi atualizada.'];
    }
}