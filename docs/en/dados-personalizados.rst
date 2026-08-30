Custom data and fields
================================

Log table: ``tool_painelava_logging``
----------------------------------------------

Defined in ``db/install.xml`` and created by ``db/migrate.php`` (called by both
``db/install.php`` and ``db/upgrade.php``):

.. list-table::
   :header-rows: 1
   :widths: 30 15 55

   * - Field
     - Type
     - Description
   * - ``id``
     - ``int(10)``
     - Primary key.
   * - ``userid``
     - ``int(10)``
     - ID of the user who made the request.
   * - ``targetuserid``
     - ``int(10)``
     - ID of the user whose courses were requested.
   * - ``user_ipaddress`` / ``targetuser_ipaddress``
     - ``char(45)``
     - IP addresses (optional).
   * - ``timecreated``
     - ``int(10)``
     - Unix *timestamp* of the request.

Indexes: ``idx_users`` (``targetuserid``, ``userid``) and ``idx_timecreated`` (``timecreated``).

.. warning::
   The table is created by the migration, but **no plugin code writes records to it**. The
   points that log something do so via a Moodle event
   (``tool_painelava\event\user_courses_requested`` — see :doc:`api-webservice`), not via an
   ``INSERT`` into this table. The table exists in the schema, but currently has no associated
   writes.

Course custom fields
------------------------------------

``migration_helpers::bulk_course_custom_field()`` (``db/migrate.php``) ensures, on every
install/upgrade, the existence of the **Painel AVA** course custom field category
(``customfield_category``, component ``core_course``, area ``course``) and these three fields:

.. list-table::
   :header-rows: 1
   :widths: 25 15 60

   * - Field (*shortname*)
     - Type
     - Use
   * - ``sala_tipo``
     - text
     - Classifies the course for grouping purposes into Painel AVA tabs — see ``get_diarios``
       in :doc:`api-http`. It is also one of the fields read by ``get_diarios.php`` and
       ``get_course_info.php`` (along with other fields the plugin **reads**, but does not
       create automatically: ``turma_ano_periodo``, ``disciplina_id``,
       ``disciplina_descricao``, ``disciplina_sigla``, ``curso_codigo``, ``curso_descricao``,
       ``diario_id`` and ``carga_horaria`` — presumably provisioned by another system or
       manually).
   * - ``turma_autoinscricao``
     - *checkbox*
     - Enables the self-enrolment check in ``group_helper`` (see below).
   * - ``restricoes_de_autoinscricao``
     - text
     - When filled in on a visible course, makes that course eligible to appear in the
       ``autoinscricoes`` tab of ``get_diarios`` (evaluating the restriction rule itself is done
       by the Painel AVA, not by Moodle — the plugin only exposes the field's raw text).

.. note::
   ``customfield_category`` is created with a fixed ``contextid = 1`` in the code (the system
   context, id ``1``, is a stable convention in standard Moodle installations, but the value is
   hardcoded instead of resolved via ``context_system::instance()->id``).

Automatic campus grouping (``group_helper``)
-----------------------------------------------------------

``tool_painelava\group_helper::ensure_user_in_profile_based_group()`` is called by
``api/enrol_course.php`` whenever an enrolment is created or reactivated. The flow:

1. Checks whether the course has the ``turma_autoinscricao`` custom field checked — if not,
   does nothing.
2. Reads the user profile field indicated by ``$fieldshortname`` (default
   ``campus_sigla`` — coming from the ``auth_suap`` plugin, not this one).
3. Normalises the value (uppercase, *trimmed*); uses ``SEM_CAMPUS`` as a fallback group when
   empty.
4. Looks up (or creates, with race-condition handling) a course group with that name via the
   native APIs ``groups_get_group_by_name()`` / ``groups_create_group()``.
5. Adds the user to the group, if not already a member.

Scheduled task
-------------------

``tool_painelava\task\sync_courses`` (``classes/task/sync_courses.php``), registered in
``db/tasks.php`` as **disabled by default**, scheduled to run daily at 03:00 when enabled.

.. warning::
   The current implementation is a placeholder: ``execute()`` only writes
   ``mtrace('tool_painelava: sync_courses task executed successfully.')`` and performs no actual
   synchronisation. The name and schedule suggest a purpose (synchronising course data) that has
   not yet been implemented.

Privacy
--------------

No ``classes/privacy/provider.php`` was found in this plugin — unlike plugins such as
``auth_suap``, ``tool_painelava`` **does not implement** Moodle's privacy API
(``core_privacy``). The data it exposes (courses, enrolments, progress, roles) is queried on
demand from Moodle core tables and is not stored additionally by the plugin, except for the
``tool_painelava_logging`` table described above (which, as noted, receives no writes in the
current code).
