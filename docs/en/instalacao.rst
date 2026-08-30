Installation
============

1. Installing the plugin
--------------------------

1. Copy (or clone) this repository's contents to
   ``<moodle_root>/admin/tool/painelava/``.
2. Visit **Site administration** in Moodle — the database upgrade runs automatically (it runs
   ``db/install.php``, which creates the ``tool_painelava_logging`` table and the **Painel AVA**
   category course custom fields — see :doc:`dados-personalizados`).

2. Configuring the plugin
----------------------------

In **Site administration → Plugins → Admin tools → Painel AVA**
(``admin/tool/painelava/settings.php``), configure:

.. list-table::
   :header-rows: 1
   :widths: 30 15 55

   * - Setting
     - Internal key
     - Description
   * - Auth token
     - ``auth_token``
     - Token that the Painel AVA must send in the ``Authentication: Token <value>`` header to
       authenticate against the HTTP endpoints in ``api/`` — see :doc:`api-http`.
   * - Painel AVA URL
     - ``painel_url``
     - Base URL of the Painel AVA (default ``https://ava.ifrn.edu.br``). Used by
       ``api/sync_user_preference.php`` to forward user preferences to the Painel AVA.
   * - Course custom field: Sala Tipo
     - ``course_custom_field_sala_tipo``
     - Described on the settings screen as the field used to identify the course's room type
       (default ``sala_tipo``).

.. warning::
   ``settings.php``/``adminlib.php`` only expose these three settings in the admin interface.
   However, much of the code in ``classes/external/get_user_courses.php`` reads additional
   settings that **do not exist on any screen** — ``coursetypefield``, ``enablelogging``,
   ``prefix_fic``, ``prefix_coordenacao``, ``prefix_laboratorio``, ``prefix_modelo`` and
   ``prefix_diario`` (see :doc:`api-webservice`). Without a UI to set them, these settings only
   take effect if manually inserted into Moodle's ``config_plugins`` table
   (``plugin = 'tool_painelava'``); otherwise the code uses the built-in default values (for
   example, ``coursetypefield`` falls back to ``'tipo_curso'`` — note that this name **does not
   match** the ``course_custom_field_sala_tipo`` key exposed on the screen, meaning the setting
   visible in the admin interface is not, in fact, the one consulted by the custom-field
   classification).

3. Capabilities
-----------------

.. list-table::
   :header-rows: 1
   :widths: 35 15 50

   * - Capability
     - Default roles
     - Purpose
   * - ``tool/painelava:view``
     - ``manager``, ``coursecreator``
     - Access to the Painel AVA administrative area in Moodle.
   * - ``tool/painelava:viewothercourses``
     - ``manager``
     - Allows querying, via ``tool_painelava_get_user_courses``, the courses of **another**
       user (without this capability, a user can only query their own courses).

Grant ``tool/painelava:viewothercourses`` only to strictly necessary roles — see also
``SECURITY.md`` in the repository.

4. Testing access
------------------------

* The web service function ``tool_painelava_get_user_courses`` is available to any enabled web
  service that includes it (it is registered, among others, in Moodle's official mobile
  service — see :doc:`api-webservice`).
* The HTTP endpoints in ``api/`` require the ``Authentication: Token <configured auth_token>``
  header on every call — see the example in ``requests.http`` and the details in
  :doc:`api-http`.

.. note::
   Unlike authentication plugins (such as ``auth_suap``), ``tool_painelava`` does not define a
   login screen or an OAuth2 flow — all integration happens via HTTP calls made by the Painel
   AVA (external application) to Moodle.
