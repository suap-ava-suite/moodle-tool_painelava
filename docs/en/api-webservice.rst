Web service API
====================

``tool_painelava`` registers a single function in Moodle's native *web services* subsystem:
``tool_painelava_get_user_courses``, implemented in
``classes/external/get_user_courses.php`` and registered in ``db/services.php``.

Service registration
-----------------------

.. list-table::
   :header-rows: 1
   :widths: 30 70

   * - Property
     - Value
   * - ``classname``
     - ``tool_painelava\external\get_user_courses``
   * - ``methodname``
     - ``execute``
   * - ``type``
     - ``read``
   * - ``ajax``
     - ``true``
   * - ``loginrequired``
     - ``true``
   * - ``services``
     - ``MOODLE_OFFICIAL_MOBILE_SERVICE`` (Moodle's official mobile app service)

Parameters
-------------

.. list-table::
   :header-rows: 1
   :widths: 20 15 15 50

   * - Parameter
     - Type
     - Default
     - Description
   * - ``userid``
     - ``PARAM_INT``
     - ``0``
     - Target user ID. ``0`` means the authenticated user (``$USER->id``).

Permissions
-------------

* A user can always query their own courses (``userid == $USER->id``, or ``userid = 0``).
* Querying **another** user's courses requires the ``tool/painelava:viewothercourses``
  capability in the system context (``require_capability``) — granted by default only to the
  ``manager`` role.
* The target user must exist and not be marked as deleted (``deleted = 0``); otherwise the
  function throws a ``moodle_exception`` with the error code ``invaliduser``.

Course classification
----------------------------

For each course the user is enrolled in (via ``enrol_get_users_courses()``), the type is
resolved by ``resolve_course_type()`` in the following priority order:

1. **Course custom field** whose *shortname* matches
   ``get_config('tool_painelava', 'coursetypefield')`` — if absent, falls back to the built-in
   default ``'tipo_curso'``. The field value is normalised (lowercase, accents handled) by
   ``normalise_type_value()`` into one of the known types.
2. If the field does not determine the type, the course's *shortname* is compared (``stripos``,
   prefix match) against the configurable prefixes ``prefix_fic`` (default ``FIC-``),
   ``prefix_coordenacao`` (default ``COORD-``), ``prefix_laboratorio`` (default ``LAB-``) and
   ``prefix_modelo`` (default ``MODELO-``); then against ``prefix_diario`` (default empty —
   therefore normally inactive).
3. If nothing matches, the type falls back to ``outros``.

.. warning::
   None of these configuration keys (``coursetypefield``, ``prefix_fic``,
   ``prefix_coordenacao``, ``prefix_laboratorio``, ``prefix_modelo``, ``prefix_diario``) has a
   corresponding field in ``settings.php`` — see the warning in :doc:`instalacao`. In practice,
   without manual editing of the ``config_plugins`` table, classification depends entirely on
   the built-in default values, and the tests in ``tests/external/external_test.php`` cover
   exactly these defaults (prefixes ``FIC-``, ``COORD-``, ``LAB-``, ``MODELO-``).

Return structure
------------------------

The response is an object with six lists, one per recognised course type:

.. code-block:: text

   {
     "diario": [...], "fic": [...], "coordenacao": [...],
     "laboratorio": [...], "modelo": [...], "outros": [...]
   }

Each course item follows this structure:

.. list-table::
   :header-rows: 1
   :widths: 25 20 55

   * - Field
     - Type
     - Description
   * - ``id``, ``shortname``, ``fullname``, ``idnumber``, ``summary``, ``summaryformat``,
       ``startdate``, ``enddate``, ``visible``, ``category``
     - —
     - Standard fields from the Moodle course record.
   * - ``course_type``
     - ``string``
     - One of ``diario``, ``fic``, ``coordenacao``, ``laboratorio``, ``modelo``, ``outros``.
   * - ``role``
     - ``string``
     - *Shortname* of the first role returned by ``get_user_roles()`` for the user in that
       course (the "main" role, in the order Moodle returns them).
   * - ``roles``
     - list of objects
     - All of the user's roles in the course: ``roleid``, ``shortname``, ``name`` (translated
       via ``role_get_name()``).
   * - ``customfields``
     - list of objects
     - All course custom fields: ``shortname``, ``name``, ``type``, ``value`` (value formatted
       for display), ``valueraw`` (raw stored value).

Triggered event
-------------------

If the ``enablelogging`` setting is enabled (see the warning above about this setting's absence
from the UI), each call triggers the ``tool_painelava\event\user_courses_requested`` event
(``classes/event/user_courses_requested.php``), registered with ``crud = 'r'`` and associated
with the user who made the call and the ``relateduserid`` (target user).

.. note::
   This is the only event defined by the plugin. ``db/events.php`` registers the list of
   *observers* the plugin listens to from other components — currently empty
   (``$observers = [];``).

Tests
---------

``tests/external/external_test.php`` covers, via ``externallib_advanced_testcase``: permissions
(a regular user cannot see another user's courses without the capability; ``manager`` can; a
deleted user throws ``invaliduser``), classification by *shortname* prefix for each of the four
configurable types, aggregation of multiple courses and the presence of all expected keys in the
return structure. Local run (from the Moodle root):

.. code-block:: bash

   vendor/bin/phpunit admin/tool/painelava/tests/external/external_test.php
