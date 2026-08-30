Dados e campos personalizados
================================

Tabela de log: ``tool_painelava_logging``
----------------------------------------------

Definida em ``db/install.xml`` e criada por ``db/migrate.php`` (chamado tanto por
``db/install.php`` quanto por ``db/upgrade.php``):

.. list-table::
   :header-rows: 1
   :widths: 30 15 55

   * - Campo
     - Tipo
     - Descrição
   * - ``id``
     - ``int(10)``
     - Chave primária.
   * - ``userid``
     - ``int(10)``
     - ID do usuário que fez a requisição.
   * - ``targetuserid``
     - ``int(10)``
     - ID do usuário cujos cursos foram solicitados.
   * - ``user_ipaddress`` / ``targetuser_ipaddress``
     - ``char(45)``
     - Endereços IP (opcionais).
   * - ``timecreated``
     - ``int(10)``
     - *Timestamp* Unix da requisição.

Índices: ``idx_users`` (``targetuserid``, ``userid``) e ``idx_timecreated`` (``timecreated``).

.. warning::
   A tabela é criada pela migração, mas **nenhum código do plugin grava registros nela**. Os
   pontos que logam algo fazem isso via evento do Moodle
   (``tool_painelava\event\user_courses_requested`` — ver :doc:`api-webservice`), não via
   ``INSERT`` nesta tabela. A tabela existe no schema, mas está, no momento, sem gravação
   associada.

Campos personalizados de curso
------------------------------------

``migration_helpers::bulk_course_custom_field()`` (``db/migrate.php``) garante, a cada
instalação/atualização, a existência da categoria de campo personalizado de curso **Painel
AVA** (``customfield_category``, componente ``core_course``, área ``course``) e destes três
campos:

.. list-table::
   :header-rows: 1
   :widths: 25 15 60

   * - Campo (*shortname*)
     - Tipo
     - Uso
   * - ``sala_tipo``
     - texto
     - Classifica o curso para fins de agrupamento em abas no Painel AVA — ver
       ``get_diarios`` em :doc:`api-http`. Também é um dos campos lidos por
       ``get_diarios.php`` e ``get_course_info.php`` (junto com outros campos que o plugin
       **lê**, mas não cria automaticamente: ``turma_ano_periodo``, ``disciplina_id``,
       ``disciplina_descricao``, ``disciplina_sigla``, ``curso_codigo``, ``curso_descricao``,
       ``diario_id`` e ``carga_horaria`` — presumivelmente provisionados por outro sistema ou
       manualmente).
   * - ``turma_autoinscricao``
     - *checkbox*
     - Habilita a verificação de autoinscrição em ``group_helper`` (ver abaixo).
   * - ``restricoes_de_autoinscricao``
     - texto
     - Quando preenchido em um curso visível, torna esse curso elegível para aparecer na aba
       ``autoinscricoes`` de ``get_diarios`` (a avaliação da regra de restrição em si é feita
       pelo Painel AVA, não pelo Moodle — o plugin apenas expõe o texto bruto do campo).

.. note::
   ``customfield_category`` é criada com ``contextid = 1`` fixo no código (o contexto de
   sistema, id ``1``, é uma convenção estável em instalações Moodle padrão, mas o valor está
   *hardcoded* em vez de resolvido via ``context_system::instance()->id``).

Agrupamento automático por campus (``group_helper``)
-----------------------------------------------------------

``tool_painelava\group_helper::ensure_user_in_profile_based_group()`` é chamado por
``api/enrol_course.php`` sempre que uma matrícula é criada ou reativada. O fluxo:

1. Verifica se o curso tem o campo personalizado ``turma_autoinscricao`` marcado — se não,
   não faz nada.
2. Lê o campo de perfil de usuário indicado por ``$fieldshortname`` (padrão
   ``campus_sigla`` — proveniente do plugin ``auth_suap``, não deste plugin).
3. Normaliza o valor (maiúsculas, *trim*); usa ``SEM_CAMPUS`` como grupo de fallback quando
   vazio.
4. Busca (ou cria, com tratamento de condição de corrida) um grupo do curso com esse nome via
   as APIs nativas ``groups_get_group_by_name()`` / ``groups_create_group()``.
5. Adiciona o usuário ao grupo, se ainda não for membro.

Tarefa agendada
-------------------

``tool_painelava\task\sync_courses`` (``classes/task/sync_courses.php``), registrada em
``db/tasks.php`` como **desabilitada por padrão**, agendada para rodar diariamente às 03h
quando habilitada.

.. warning::
   A implementação atual é um placeholder: ``execute()`` apenas escreve
   ``mtrace('tool_painelava: sync_courses task executed successfully.')`` e não executa nenhuma
   sincronização real. O nome e o agendamento sugerem um propósito (sincronizar dados de curso)
   que ainda não foi implementado.

Privacidade
--------------

Não foi encontrado nenhum ``classes/privacy/provider.php`` neste plugin — ao contrário de
plugins como ``auth_suap``, ``tool_painelava`` **não implementa** a API de privacidade
(``core_privacy``) do Moodle. Os dados que ele expõe (cursos, matrículas, progresso, papéis)
são consultados sob demanda a partir de tabelas *core* do Moodle e não são armazenados de forma
adicional pelo plugin, exceto pela tabela ``tool_painelava_logging`` descrita acima (que, como
observado, não recebe gravações no código atual).
