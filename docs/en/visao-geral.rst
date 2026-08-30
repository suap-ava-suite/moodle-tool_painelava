Overview
========

What the plugin does
---------------------

``tool_painelava`` is an *Admin tool* plugin (installed at ``admin/tool/painelava``) that acts
as a bridge between Moodle and the **Painel AVA** (an external application, referred to in the
code as "the Django panel"). It offers two clearly distinct integration surfaces:

* a **native Moodle web service function** (``tool_painelava_get_user_courses``), registered in
  the core *web services* subsystem and protected by Moodle's capability mechanism — see
  :doc:`api-webservice`;
* a set of **dedicated HTTP endpoints**, outside the *web services* subsystem, authenticated by
  a simple token sent in an HTTP header, used for operations that the web service API does not
  cover (enrolment, suspension, progress, preferences, favourites, course visibility, etc.) —
  see :doc:`api-http`.

In addition, the plugin automatically creates course custom fields (category **Painel AVA**)
used to classify courses and control self-enrolment, registers its own call log table
(``tool_painelava_logging``) and provides a scheduled task — see :doc:`dados-personalizados`.

Requirements
------------

* Moodle 4.5.0+ (``$plugin->requires`` = ``2024100710`` in ``version.php``).
* The CI pipeline (``.github/workflows/ci.yml``) tests the plugin against
  ``MOODLE_405_STABLE``, ``MOODLE_500_STABLE`` and ``MOODLE_501_STABLE``, with PHP 8.1 to 8.4
  (some combinations excluded due to incompatibility — PHP 8.4 is not tested with Moodle 4.5,
  PHP 8.1 is not tested with Moodle 5.0+).

.. warning::
   The repository's ``README.md`` states that the requirements are "Moodle 4.0 or higher
   (``requires = 2022041900``)" and "PHP 7.4+". These values are outdated: the current
   ``version.php`` defines ``requires = 2024100710`` (Moodle 4.5) and the CI matrix only tests
   PHP 8.1 onwards. This documentation follows what is actually in the code.

Implicit (optional) dependencies
----------------------------------

The plugin does not declare formal dependencies in ``version.php``, but part of the code only
has its full effect if the ``local_suap`` plugin is installed:

* ``api/enrol_course.php`` uses ``get_config('local_suap', 'default_auth')`` (falling back to
  ``manual``) and ``get_config('local_suap', 'default_user_preferences')`` when creating a user
  on demand (*JIT provisioning* — see :doc:`api-http`).
* ``classes/group_helper.php`` reads the profile field ``campus_sigla``, which is created by the
  ``auth_suap`` plugin (not by ``tool_painelava``) — if that field does not exist, grouping by
  campus simply falls back to the default value ``SEM_CAMPUS``.

Repository structure
----------------------

.. code-block:: text

   tool_painelava/
   ├── adminlib.php                        # Admin settings page class
   ├── settings.php                        # Settings page registration
   ├── locallib.php                        # Generic helpers (config, aget, get_or_create...)
   ├── version.php                         # Plugin version/release/maturity
   ├── api/                                 # Dedicated HTTP endpoints (token authentication)
   │   ├── index.php                        # v1 dispatcher (service whitelist)
   │   ├── servicelib.php                   # Base class tool_painelava\service
   │   ├── get_diarios.php                  # User course timeline, with filters/tabs
   │   ├── get_progresso.php                # Completion percentage per course
   │   ├── get_atualizacoes_counts.php      # Unread message/notification counts
   │   ├── get_course_info.php              # Course details, teachers and workload
   │   ├── set_favourite_course.php         # Marks/unmarks a course as favourite
   │   ├── set_visible_course.php           # Changes course visibility
   │   ├── set_user_preference.php          # Saves a user preference
   │   ├── sync_user_preference.php         # Forwards a preference to the Painel AVA (Django)
   │   ├── enrol_course.php                 # Enrols (or creates and enrols) a user
   │   ├── suspend_enrol.php                # Suspends a user's enrolment
   │   └── v2/                              # v2 dispatcher (endpoints still placeholder)
   │       ├── index.php                    # v2 dispatcher (own whitelist)
   │       ├── get_conversas.php, get_notificacoes.php, get_salas.php
   │       ├── patch_conversa.php, patch_notificacao.php
   │       └── token_refresh.php, token_revoke.php
   ├── classes/
   │   ├── external/get_user_courses.php    # Web service function implementation
   │   ├── event/user_courses_requested.php # Event triggered by the web service function
   │   ├── task/sync_courses.php            # Scheduled task (placeholder)
   │   └── group_helper.php                 # Automatic campus grouping on self-enrolment
   ├── db/
   │   ├── access.php                       # Capabilities (tool/painelava:view, :viewothercourses)
   │   ├── services.php                     # Web service function registration
   │   ├── tasks.php                        # Scheduled task registration
   │   ├── events.php                       # Event observers (currently empty)
   │   ├── install.php / upgrade.php        # Both delegate to migrate.php
   │   ├── migrate.php                      # Creates the log table and course custom fields
   │   ├── install.xml                      # Schema for the tool_painelava_logging table
   │   └── uninstall.php                    # Removes plugin settings
   ├── lang/{en,pt_br}/tool_painelava.php   # Language strings
   ├── tests/external/external_test.php     # PHPUnit tests for the web service function
   ├── requests.http                        # Example calls to the HTTP endpoints (api/index.php)
   ├── test_debug.php                       # CLI debugging script (not part of the API)
   ├── docs/                                 # This documentation (Sphinx)
   └── .github/workflows/
       ├── ci.yml                            # moodle-plugin-ci (lint, PHPCS, PHPUnit, Behat...)
       ├── release.yml                       # Builds an installable ZIP on every tag
       └── docs.yml                          # Publishes this documentation to GitHub Pages

.. note::
   ``test_debug.php``, at the repository root, is a debugging script run via CLI
   (``CLI_SCRIPT``) to manually inspect a user's course timeline. It is not registered anywhere
   in the plugin (it does not appear in ``db/services.php`` nor in ``api/index.php``) and is not
   part of the documented API surface — it is merely a development support tool.

Organisation
------------

The repository lives in the `suap-ava-suite <https://github.com/suap-ava-suite>`_ organisation
as ``moodle-tool_painelava``, alongside other components of the AVA/SUAP suite used by IFRN
(for example, ``auth_suap``, referenced above as the source of the ``campus_sigla`` profile
field).
