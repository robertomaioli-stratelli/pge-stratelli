# Mapeamento do MVP para produção

O MVP usa uma única execução PHP com perfil/município escolhidos por query string e sessão. Na produção:

- `$perfil` deixa de vir de `$_GET` e passa a ser derivado de `usuarios.grupo` + `administrador_plataforma`.
- `$municipioId` deixa de vir de `$_GET`/sessão e passa a vir do usuário autenticado ou, para Stratelli, de uma instância aberta através do slug validado.
- `secretarias`, `departamentos`, `fases`, `requisitos_documentais`, modelos, documentos, histórico e cronograma ganham `municipio_id`.
- downloads deixam de apontar para arquivos por query string sem tenant scope e serão servidos por rota protegida.
- o grande `elseif($view===...)` será dividido em Controllers/Services/Views.

A pasta `legacy/` contém apenas uma cópia de referência do MVP anexado e nunca deve ser exposta pelo servidor web.
