API HTTP
=========

Além da função de serviço web descrita em :doc:`api-webservice`, o plugin expõe um conjunto de
endpoints HTTP próprios, fora do subsistema de *web services* do Moodle, usados pelo Painel AVA
para operações que essa função não cobre. Eles vivem em ``api/`` (v1) e ``api/v2/`` (v2, ainda
em desenvolvimento).

Mecanismo de despacho e autenticação
----------------------------------------

Cada versão tem um único ponto de entrada (``api/index.php`` e ``api/v2/index.php``) que:

1. Lê o **primeiro** parâmetro da *query string* (``$_SERVER["QUERY_STRING"]`` dividida por
   ``&``) como nome do serviço — por isso as chamadas usam a forma
   ``?nome_do_servico&outro_param=valor`` (o nome do serviço não é ``nome=valor``, é apenas o
   primeiro token da *query string*, como em ``requests.http``).
2. Verifica esse nome contra uma lista fixa (*whitelist*) de serviços permitidos.
3. Inclui o arquivo ``{servico}.php`` correspondente e instancia a classe
   ``{namespace}\{servico}_service``, que estende ``tool_painelava\service``
   (``api/servicelib.php``).
4. Chama ``service->call()``, que primeiro executa ``authenticate()`` e, se bem-sucedida,
   ``do_call()`` — cujo retorno é serializado como JSON.

.. code-block:: php

   // api/servicelib.php — autenticação usada por todos os serviços v1 e v2.
   public function authenticate() {
       $syncupautotoken = config('auth_token');
       $headers = getallheaders();
       $authenticationkey = array_key_exists('Authentication', $headers) ? "Authentication" : "authentication";
       if (!array_key_exists($authenticationkey, $headers)) {
           throw new \Exception("Bad Request - Authentication not informed", 400);
       }
       if ("Token $syncupautotoken" != $headers[$authenticationkey]) {
           throw new \Exception("Unauthorized", 401);
       }
   }

Ou seja, toda chamada precisa do cabeçalho ``Authentication: Token <auth_token>``, onde
``<auth_token>`` é o valor configurado em **Administração do site → Plugins → Ferramentas de
administração → Painel AVA** (ver :doc:`instalacao`). Erros (autenticação, parâmetros ausentes,
exceções internas) são capturados por um manipulador global que responde em JSON no formato
``{"error": {"message": ..., "code": ...}}`` com o código HTTP correspondente.

.. warning::
   Este mecanismo não usa o *sesskey*/CSRF nem o controle de capacidades do Moodle — a única
   barreira é o token compartilhado. ``NO_MOODLE_COOKIES`` é definido antes de carregar
   ``config.php``, então nenhuma sessão de navegador é considerada.

Endpoints v1 (``api/``)
---------------------------

.. list-table::
   :header-rows: 1
   :widths: 25 75

   * - Serviço
     - Arquivo / status
   * - ``get_diarios``
     - ``api/get_diarios.php`` — implementado
   * - ``get_progresso``
     - ``api/get_progresso.php`` — implementado
   * - ``get_atualizacoes_counts``
     - ``api/get_atualizacoes_counts.php`` — implementado
   * - ``get_course_info``
     - ``api/get_course_info.php`` — implementado
   * - ``set_favourite_course``
     - ``api/set_favourite_course.php`` — implementado
   * - ``set_visible_course``
     - ``api/set_visible_course.php`` — implementado
   * - ``set_user_preference``
     - ``api/set_user_preference.php`` — implementado
   * - ``sync_user_preference``
     - ``api/sync_user_preference.php`` — implementado (mas não segue o padrão ``service``, ver
       nota abaixo)
   * - ``enrol_course``
     - ``api/enrol_course.php`` — implementado
   * - ``suspend_enrol``
     - ``api/suspend_enrol.php`` — implementado
   * - ``sync_up_enrolments``
     - **sem arquivo correspondente**
   * - ``sync_down_grades``
     - **sem arquivo correspondente**

.. danger::
   ``sync_up_enrolments`` e ``sync_down_grades`` constam na *whitelist* de
   ``api/index.php``, mas não existe ``api/sync_up_enrolments.php`` nem
   ``api/sync_down_grades.php`` no repositório. Uma chamada a esses dois nomes de serviço passa
   pela *whitelist*, falha no ``require_once`` do arquivo inexistente e resulta em erro fatal do
   PHP (não em uma resposta JSON de erro controlada, já que isso ocorre fora do bloco
   ``try/catch`` de classe). Trata-se de funcionalidade referenciada, mas nunca implementada.

``get_diarios``
~~~~~~~~~~~~~~~~~~

O endpoint mais complexo do plugin: monta a "timeline" de cursos do usuário na visão do Painel
AVA, agrupada em abas dinâmicas conforme o campo personalizado ``sala_tipo`` de cada curso
(ex.: ``diarios``, ou qualquer outro valor presente nesse campo — exceto ``autoinscricoes``,
tratado como um alias de ``diarios``), mais uma aba especial ``autoinscricoes`` com cursos em
"vitrine" disponíveis para autoinscrição — ver :doc:`dados-personalizados`.

.. list-table::
   :header-rows: 1
   :widths: 20 15 65

   * - Parâmetro (``$_GET``)
     - Padrão
     - Descrição
   * - ``username``
     - —
     - Username Moodle do usuário alvo. Se não encontrado, o retorno traz listas vazias.
   * - ``semestre``, ``disciplina``, ``curso``, ``q``
     - ``null``
     - Filtros aplicados apenas à aba ``diarios`` (``q`` casa contra *shortname*/*fullname*).
   * - ``situacao``
     - ``null``
     - Um de ``all``, ``allincludinghidden``, ``inprogress``, ``past``, ``future``,
       ``favourites`` — filtra a *timeline* de matrículas do Moodle antes do agrupamento.
   * - ``ordenacao``
     - ``null``
     - Repassado como ordenação SQL para ``enrol_get_my_courses()``.
   * - ``arquetipo``
     - ``student``
     - Recebido, mas não utilizado no corpo do método ``get_diarios()`` atual.
   * - ``page``, ``page_size``
     - ``1``, ``9``
     - Recebidos, mas **não aplicados** à consulta nem à resposta — não há paginação real
       implementada apesar de os parâmetros existirem.

Retorna um objeto com ``semestres``, ``disciplinas`` e ``cursos`` (listas de opções, extraídas
dos campos personalizados de todos os diários do usuário, para uso em filtros no Painel AVA),
mais uma chave por "aba" identificada (tipicamente ``diarios``, e qualquer outro valor de
``sala_tipo`` presente, incluindo ``autoinscricoes`` quando há cursos elegíveis).

``get_progresso``
~~~~~~~~~~~~~~~~~~~~

.. list-table::
   :header-rows: 1
   :widths: 20 15 65

   * - Parâmetro
     - Padrão
     - Descrição
   * - ``username``
     - —
     - Username Moodle. Se não encontrado, retorna lista vazia.
   * - ``courseids``
     - ``null``
     - Lista de IDs de curso separados por vírgula; se omitido, considera todas as matrículas
       ativas do usuário.

Retorna, para cada curso com conclusão de curso habilitada
(``enablecompletion == COMPLETION_ENABLED``), ``id``, ``progress`` (inteiro arredondado, ou
``null``) e ``hasprogress`` (booleano). O tempo total de execução é registrado via
``error_log()`` com o prefixo ``[PROFILER - TOTAL]`` — não removido de produção.

``get_atualizacoes_counts``
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Recebe ``username`` e retorna a contagem de conversas não lidas
(``core_message_external::get_unread_conversations_count``) e notificações *popup* não lidas
(``message_popup_external::get_unread_popup_notification_count``) do usuário. Se o usuário não
existir, retorna um corpo com ``error`` preenchido, mas ainda assim com HTTP 200 (o código de
erro só aparece dentro do JSON, não no *status* HTTP).

``get_course_info``
~~~~~~~~~~~~~~~~~~~~~~

Recebe ``courseid`` e (opcionalmente) ``username``. Retorna ``id``, ``fullname``, ``shortname``,
``summary`` (formatado via ``format_text``), ``is_enrolled`` (se ``username`` informado e
matriculado ativamente), a lista ``docentes`` (nome, foto e descrição de todo usuário com a
capacidade ``moodle/course:update`` no curso, ordenada alfabeticamente) e ``carga_horaria``
(lida do campo personalizado de curso ``carga_horaria``, tentando ``decvalue``, depois
``charvalue``, depois ``intvalue``).

``set_favourite_course`` / ``set_visible_course``
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Ambos recebem ``username`` e ``courseid``:

* ``set_favourite_course`` também recebe ``favourite`` (``1``/``0``/``true``/``false``); valida
  o formato do ``username`` (regex ``^[a-z0-9._@+-]+$``, máx. 100 caracteres), assume a
  identidade do usuário via ``\core\session\manager::set_user()`` e delega para
  ``core_course_external::set_favourite_courses()``.
* ``set_visible_course`` também recebe ``visible``; exige que o usuário identificado por
  ``username`` tenha a capacidade ``moodle/course:visibility`` no contexto do curso (senão
  lança HTTP 403) e então atualiza ``course.visible`` diretamente via ``$DB->update_record()``.

``set_user_preference``
~~~~~~~~~~~~~~~~~~~~~~~~~~~

Recebe ``username``, ``name`` e ``value`` (todos obrigatórios). Normaliza ``value`` para
``'1'``/``'0'`` quando reconhecido como booleano, para inteiro quando numérico, ou mantém como
string, e grava via ``set_user_preference()`` (API nativa do Moodle).

``sync_user_preference``
~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. note::
   Diferente dos demais, este arquivo **não** define uma classe ``*_service`` — ele é um script
   autocontido que faz o caminho inverso dos outros: em vez de o Painel AVA chamar o Moodle,
   ``sync_user_preference.php`` é chamado (presumivelmente por uma ação do usuário no Moodle) e
   ele mesmo faz uma requisição HTTP de saída para o Painel AVA
   (``{painel_url}/api/v1/set_user_preference/``), autenticando-se com o mesmo ``auth_token``
   como *Bearer*/``Authorization: Token``. Requer ``category``, ``key`` e ``value`` via GET;
   usa o usuário Moodle autenticado (``$USER->username``) como identificador.

``enrol_course``
~~~~~~~~~~~~~~~~~~~

Matricula ``username`` em ``courseid``. Se o usuário não existir, ele é **criado sob demanda**
("JIT provisioning") com os dados opcionais ``firstname``, ``lastname``, ``email`` (padrão
``{username}@sememail.ifrn.edu.br``) e ``campus``, usando ``auth =
get_config('local_suap', 'default_auth')`` (ou ``manual`` se ``local_suap`` não estiver
instalado) e aplicando ``default_user_preferences`` de ``local_suap``, se configuradas. Em
seguida:

* se já existir matrícula ativa, retorna ``already_enrolled`` sem alterar nada;
* se existir matrícula suspensa, reativa-a (``status = 0``) e retorna ``reactivated``;
* caso contrário, cria uma matrícula manual nova (``roleid = 5``, papel *student*) e retorna
  ``enrolled``.

Em qualquer caminho que resulte em matrícula ativa, chama
``group_helper::ensure_user_in_profile_based_group()`` — ver :doc:`dados-personalizados`.

``suspend_enrol``
~~~~~~~~~~~~~~~~~~~~

Recebe ``username`` e ``courseid``. Se não houver matrícula, retorna ``not_enrolled``; se já
suspensa, retorna ``already_suspended``; caso contrário, suspende (``status = 1``) via
``$plugin->update_user_enrol()`` e retorna ``suspended``. Diferente de uma remoção de matrícula,
os dados de progresso do aluno são preservados.

Endpoints v2 (``api/v2/``)
-------------------------------

``api/v2/index.php`` é um dispatcher independente, com sua própria *whitelist*
(``get_notificacoes``, ``patch_notificacao``, ``get_conversas``, ``patch_conversa``,
``get_salas``, ``token_refresh``, ``token_revoke``) e o mesmo mecanismo de autenticação por
token (herdado de ``tool_painelava\service``).

.. warning::
   Todos os sete endpoints v2 existem como arquivo e respondem sem erro, mas **todas as
   implementações atuais são placeholders** — não consultam o banco de dados de forma
   significativa nem produzem dados reais:

   .. list-table::
      :header-rows: 1
      :widths: 25 75

      * - Serviço
        - Comportamento atual
      * - ``get_conversas``
        - Sempre retorna ``[]``.
      * - ``get_salas``
        - Sempre retorna ``[]``.
      * - ``get_notificacoes``
        - Busca o usuário por ``username``, mas sempre retorna ``{"result": [], "unreadcount":
          0}`` independentemente do que encontrar.
      * - ``patch_conversa``
        - Sempre retorna ``[{"error": false, "data": null}]``.
      * - ``patch_notificacao``
        - Sempre retorna uma estrutura fixa com ``notificationid = 0`` e ``warnings = []``.
      * - ``token_refresh``
        - Sempre retorna ``{"status": "success", "refreshed": true}``.
      * - ``token_revoke``
        - Sempre retorna ``{"status": "success", "revoked": true}``.

   Nenhum desses efetivamente lê parâmetros de entrada além de, no máximo, ``username`` (em
   ``get_notificacoes``, sem uso no resultado). Trate a API v2 como uma interface ainda em
   construção, não como uma integração funcional.

Exemplo de chamada
-----------------------

``requests.http`` (raiz do repositório) documenta um exemplo real de chamada v1:

.. code-block:: text

   GET http://moodle/admin/tool/painelava/api/?get_diarios&username=admin&situacao=inprogress
   Authentication: Token changeme
