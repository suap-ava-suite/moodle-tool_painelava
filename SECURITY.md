# Política de Segurança

## Versões com Suporte

Apenas a versão mais recente do plugin recebe correções de segurança.

| Versão | Com suporte       |
|--------|-------------------|
| 4.5.x  | ✅ Sim (atual)    |
| < 4.5  | ❌ Não            |

## Relatando uma Vulnerabilidade

Se você descobriu uma vulnerabilidade de segurança neste plugin, **não abra uma issue pública**. Siga as etapas abaixo:

1. **Envie um e-mail** para a equipe de desenvolvimento do IFRN descrevendo o problema:
   - Assunto: `[SECURITY] moodle-tool_painelava – <resumo breve>`
   - Descrição detalhada da vulnerabilidade
   - Passos para reprodução
   - Impacto potencial
   - Versão do plugin e do Moodle afetadas
   - (Opcional) Sugestão de correção ou prova de conceito

2. **Aguarde a confirmação.** Você receberá um retorno em até **5 dias úteis** confirmando o recebimento e indicando os próximos passos.

3. **Processo de correção.** Após a confirmação, trabalharemos em conjunto para validar, corrigir e divulgar a vulnerabilidade de forma responsável. O prazo-alvo para disponibilizar uma correção é de **30 dias** após a confirmação.

4. **Divulgação coordenada.** A vulnerabilidade será divulgada publicamente somente após a publicação de uma versão corrigida, salvo acordo diferente com o pesquisador.

## Escopo

Este projeto é um plugin para o **Moodle** (Admin Tool). As vulnerabilidades de interesse incluem, mas não se limitam a:

- Escalada de privilégios ou burla de verificações de capacidade (`tool/painelava:viewothercourses`)
- Exposição indevida de dados de cursos ou usuários através da API externa (`tool_painelava_get_user_courses`)
- Vazamento ou manipulação do `auth_token` de configuração
- Injeção SQL ou execução remota de código no contexto do plugin
- Cross-Site Scripting (XSS) ou Cross-Site Request Forgery (CSRF) introduzidos pelo plugin

Vulnerabilidades no **Moodle core** ou em outros plugins devem ser reportadas diretamente ao [programa de segurança do Moodle](https://moodle.org/security/).

## Boas Práticas para Quem Usa o Plugin

- Mantenha o plugin sempre atualizado para a versão mais recente.
- Mantenha o Moodle atualizado, aplicando todos os patches de segurança oficiais.
- Armazene o `auth_token` de forma segura; nunca o exponha em logs ou repositórios de código.
- Conceda a capacidade `tool/painelava:viewothercourses` apenas aos papéis estritamente necessários.
- Restrinja o acesso ao serviço web externo do Moodle a IPs ou redes confiáveis sempre que possível.

## Créditos

Agradecemos a todos que contribuem para a segurança deste projeto de forma responsável.

---

© 2026 Kelson da Costa Medeiros – Licença [GNU GPL v3 ou superior](http://www.gnu.org/copyleft/gpl.html)
