HTTP API
=========

In addition to the web service function described in :doc:`api-webservice`, the plugin exposes
a set of dedicated HTTP endpoints, outside Moodle's *web services* subsystem, used by the Painel
AVA for operations that function does not cover. They live in ``api/`` (v1) and ``api/v2/``
(v2, still under development).

Dispatch and authentication mechanism
----------------------------------------

Each version has a single entry point (``api/index.php`` and ``api/v2/index.php``) that:

1. Reads the **first** parameter of the query string (``$_SERVER["QUERY_STRING"]`` split by
   ``&``) as the service name — which is why calls use the form
   ``?service_name&other_param=value`` (the service name is not ``name=value``, it is just the
   first token of the query string, as shown in ``requests.http``).
2. Checks that name against a fixed *whitelist* of allowed services.
3. Includes the corresponding ``{service}.php`` file and instantiates the
   ``{namespace}\{service}_service`` class, which extends ``tool_painelava\service``
   (``api/servicelib.php``).
4. Calls ``service->call()``, which first runs ``authenticate()`` and, if successful,
   ``do_call()`` — whose return value is serialised as JSON.

.. code-block:: php

   // api/servicelib.php — authentication used by all v1 and v2 services.
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

In other words, every call needs the ``Authentication: Token <auth_token>`` header, where
``<auth_token>`` is the value configured in **Site administration → Plugins → Admin tools →
Painel AVA** (see :doc:`instalacao`). Errors (authentication, missing parameters, internal
exceptions) are caught by a global handler that responds in JSON in the format
``{"error": {"message": ..., "code": ...}}`` with the corresponding HTTP status code.

.. warning::
   This mechanism does not use Moodle's *sesskey*/CSRF or capability checks — the only barrier
   is the shared token. ``NO_MOODLE_COOKIES`` is defined before loading ``config.php``, so no
   browser session is considered.

v1 endpoints (``api/``)
---------------------------

.. list-table::
   :header-rows: 1
   :widths: 25 75

   * - Service
     - File / status
   * - ``get_diarios``
     - ``api/get_diarios.php`` — implemented
   * - ``get_progresso``
     - ``api/get_progresso.php`` — implemented
   * - ``get_atualizacoes_counts``
     - ``api/get_atualizacoes_counts.php`` — implemented
   * - ``get_course_info``
     - ``api/get_course_info.php`` — implemented
   * - ``set_favourite_course``
     - ``api/set_favourite_course.php`` — implemented
   * - ``set_visible_course``
     - ``api/set_visible_course.php`` — implemented
   * - ``set_user_preference``
     - ``api/set_user_preference.php`` — implemented
   * - ``sync_user_preference``
     - ``api/sync_user_preference.php`` — implemented (but does not follow the ``service``
       pattern, see the note below)
   * - ``enrol_course``
     - ``api/enrol_course.php`` — implemented
   * - ``suspend_enrol``
     - ``api/suspend_enrol.php`` — implemented
   * - ``sync_up_enrolments``
     - **no corresponding file**
   * - ``sync_down_grades``
     - **no corresponding file**

.. danger::
   ``sync_up_enrolments`` and ``sync_down_grades`` are listed in ``api/index.php``'s
   *whitelist*, but neither ``api/sync_up_enrolments.php`` nor ``api/sync_down_grades.php``
   exist in the repository. A call to either of these two service names passes the *whitelist*,
   fails on the ``require_once`` of the non-existent file and results in a fatal PHP error (not
   a controlled JSON error response, since this happens outside the class's ``try/catch``
   block). This is functionality that is referenced but never implemented.

``get_diarios``
~~~~~~~~~~~~~~~~~~

The plugin's most complex endpoint: it builds the user's course "timeline" as seen by the
Painel AVA, grouped into dynamic tabs according to each course's ``sala_tipo`` custom field
(e.g. ``diarios``, or any other value present in that field — except ``autoinscricoes``, which
is treated as an alias of ``diarios``), plus a special ``autoinscricoes`` tab with "showcase"
courses available for self-enrolment — see :doc:`dados-personalizados`.

.. list-table::
   :header-rows: 1
   :widths: 20 15 65

   * - Parameter (``$_GET``)
     - Default
     - Description
   * - ``username``
     - —
     - Target user's Moodle username. If not found, the response returns empty lists.
   * - ``semestre``, ``disciplina``, ``curso``, ``q``
     - ``null``
     - Filters applied only to the ``diarios`` tab (``q`` matches against
       *shortname*/*fullname*).
   * - ``situacao``
     - ``null``
     - One of ``all``, ``allincludinghidden``, ``inprogress``, ``past``, ``future``,
       ``favourites`` — filters Moodle's enrolment timeline before grouping.
   * - ``ordenacao``
     - ``null``
     - Passed through as SQL ordering to ``enrol_get_my_courses()``.
   * - ``arquetipo``
     - ``student``
     - Received, but not used in the body of the current ``get_diarios()`` method.
   * - ``page``, ``page_size``
     - ``1``, ``9``
     - Received, but **not applied** to the query or the response — no real pagination is
       implemented despite the parameters existing.

Returns an object with ``semestres``, ``disciplinas`` and ``cursos`` (option lists, extracted
from the custom fields of all of the user's "diários", for use in Painel AVA filters), plus one
key per identified "tab" (typically ``diarios``, and any other ``sala_tipo`` value present,
including ``autoinscricoes`` when there are eligible courses).

``get_progresso``
~~~~~~~~~~~~~~~~~~~~

.. list-table::
   :header-rows: 1
   :widths: 20 15 65

   * - Parameter
     - Default
     - Description
   * - ``username``
     - —
     - Moodle username. If not found, returns an empty list.
   * - ``courseids``
     - ``null``
     - Comma-separated list of course IDs; if omitted, considers all of the user's active
       enrolments.

For each course with course completion enabled (``enablecompletion ==
COMPLETION_ENABLED``), returns ``id``, ``progress`` (rounded integer, or ``null``) and
``hasprogress`` (boolean). Total execution time is logged via ``error_log()`` with the
``[PROFILER - TOTAL]`` prefix — not removed from production.

``get_atualizacoes_counts``
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Takes ``username`` and returns the user's unread conversation count
(``core_message_external::get_unread_conversations_count``) and unread *popup* notification
count (``message_popup_external::get_unread_popup_notification_count``). If the user does not
exist, returns a body with ``error`` populated, but still with HTTP 200 (the error code only
appears inside the JSON, not in the HTTP status).

``get_course_info``
~~~~~~~~~~~~~~~~~~~~~~

Takes ``courseid`` and (optionally) ``username``. Returns ``id``, ``fullname``, ``shortname``,
``summary`` (formatted via ``format_text``), ``is_enrolled`` (if ``username`` is given and
actively enrolled), the ``docentes`` list (name, photo and description of every user with the
``moodle/course:update`` capability in the course, sorted alphabetically) and ``carga_horaria``
(read from the ``carga_horaria`` course custom field, trying ``decvalue``, then ``charvalue``,
then ``intvalue``).

``set_favourite_course`` / ``set_visible_course``
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Both take ``username`` and ``courseid``:

* ``set_favourite_course`` also takes ``favourite`` (``1``/``0``/``true``/``false``); validates
  the ``username`` format (regex ``^[a-z0-9._@+-]+$``, max. 100 characters), assumes the user's
  identity via ``\core\session\manager::set_user()`` and delegates to
  ``core_course_external::set_favourite_courses()``.
* ``set_visible_course`` also takes ``visible``; requires the user identified by ``username`` to
  have the ``moodle/course:visibility`` capability in the course context (otherwise throws HTTP
  403) and then updates ``course.visible`` directly via ``$DB->update_record()``.

``set_user_preference``
~~~~~~~~~~~~~~~~~~~~~~~~~~~

Takes ``username``, ``name`` and ``value`` (all required). Normalises ``value`` to
``'1'``/``'0'`` when recognised as boolean, to an integer when numeric, or keeps it as a string,
and saves it via ``set_user_preference()`` (native Moodle API).

``sync_user_preference``
~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. note::
   Unlike the others, this file does **not** define a ``*_service`` class — it is a
   self-contained script that goes the opposite direction of the others: instead of the Painel
   AVA calling Moodle, ``sync_user_preference.php`` is called (presumably by a user action in
   Moodle) and it in turn makes an outbound HTTP request to the Painel AVA
   (``{painel_url}/api/v1/set_user_preference/``), authenticating with the same ``auth_token``
   as a *Bearer*/``Authorization: Token``. Requires ``category``, ``key`` and ``value`` via GET;
   uses the authenticated Moodle user (``$USER->username``) as the identifier.

``enrol_course``
~~~~~~~~~~~~~~~~~~~

Enrols ``username`` in ``courseid``. If the user does not exist, they are **created on demand**
("JIT provisioning") with the optional data ``firstname``, ``lastname``, ``email`` (default
``{username}@sememail.ifrn.edu.br``) and ``campus``, using ``auth =
get_config('local_suap', 'default_auth')`` (or ``manual`` if ``local_suap`` is not installed)
and applying ``local_suap``'s ``default_user_preferences``, if configured. Then:

* if an active enrolment already exists, returns ``already_enrolled`` without changing anything;
* if a suspended enrolment exists, reactivates it (``status = 0``) and returns ``reactivated``;
* otherwise, creates a new manual enrolment (``roleid = 5``, *student* role) and returns
  ``enrolled``.

On any path that results in an active enrolment, calls
``group_helper::ensure_user_in_profile_based_group()`` — see :doc:`dados-personalizados`.

``suspend_enrol``
~~~~~~~~~~~~~~~~~~~~

Takes ``username`` and ``courseid``. If there is no enrolment, returns ``not_enrolled``; if
already suspended, returns ``already_suspended``; otherwise, suspends it (``status = 1``) via
``$plugin->update_user_enrol()`` and returns ``suspended``. Unlike removing an enrolment, the
student's progress data is preserved.

v2 endpoints (``api/v2/``)
-------------------------------

``api/v2/index.php`` is an independent dispatcher, with its own *whitelist*
(``get_notificacoes``, ``patch_notificacao``, ``get_conversas``, ``patch_conversa``,
``get_salas``, ``token_refresh``, ``token_revoke``) and the same token-based authentication
mechanism (inherited from ``tool_painelava\service``).

.. warning::
   All seven v2 endpoints exist as files and respond without error, but **all current
   implementations are placeholders** — they do not query the database in any meaningful way
   nor produce real data:

   .. list-table::
      :header-rows: 1
      :widths: 25 75

      * - Service
        - Current behaviour
      * - ``get_conversas``
        - Always returns ``[]``.
      * - ``get_salas``
        - Always returns ``[]``.
      * - ``get_notificacoes``
        - Looks up the user by ``username``, but always returns ``{"result": [], "unreadcount":
          0}`` regardless of what it finds.
      * - ``patch_conversa``
        - Always returns ``[{"error": false, "data": null}]``.
      * - ``patch_notificacao``
        - Always returns a fixed structure with ``notificationid = 0`` and ``warnings = []``.
      * - ``token_refresh``
        - Always returns ``{"status": "success", "refreshed": true}``.
      * - ``token_revoke``
        - Always returns ``{"status": "success", "revoked": true}``.

   None of them actually read input parameters beyond, at most, ``username`` (in
   ``get_notificacoes``, unused in the result). Treat the v2 API as an interface still under
   construction, not a functional integration.

Example call
-----------------------

``requests.http`` (repository root) documents a real example of a v1 call:

.. code-block:: text

   GET http://moodle/admin/tool/painelava/api/?get_diarios&username=admin&situacao=inprogress
   Authentication: Token changeme
