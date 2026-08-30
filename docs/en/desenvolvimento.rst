Development
================

Development environment
--------------------------------

Per ``GEMINI.md`` (repository root):

* *Backend*: Moodle.
* Database: PostgreSQL.
* The project uses ``sas`` as a shortcut for ``docker compose``.
* To run code inside the container: ``sas exec moodle``.
* ``python`` is accepted as a command (for example, to run helper scripts inside the
  container).

.. note::
   There is no ``AGENTS.md`` or ``CLAUDE.md`` in this repository — only ``GEMINI.md``, with the
   content summarised above. It does not define versioning or commit message rules; those
   rules, where they exist, were inferred below from what CI actually validates
   (``moodle-plugin-ci savepoints`` and ``release.yml``).

Versioning
-------------

``.github/workflows/ci.yml`` runs ``moodle-plugin-ci savepoints``, which checks the consistency
between changes in ``db/`` (schema, *upgrade steps*) and the increment of
``$plugin->version`` in ``version.php``. As a convention observed in the current file:

* ``$plugin->version`` follows the ``YYYY_MM_DD_XXX`` pattern.
* ``$plugin->release`` follows the ``4.5.XXX`` pattern.
* ``XXX`` is the same value in both fields.

``.github/workflows/release.yml`` reinforces this rule at release time: it validates that the
last 3 digits of ``$plugin->version`` match the suffix of ``$plugin->release``, and that
``$plugin->release`` matches exactly the Git tag name used to trigger the workflow.

.. note::
   Changes **only** to ``docs/`` (like this documentation) do not touch ``db/`` or ``lang/``
   and therefore do not require a ``version.php`` increment — neither ``savepoints`` nor
   ``release.yml`` evaluate the contents of ``docs/``.

Pre-commit
-------------

Like other plugins in the suite (for example, ``auth_suap``), this repository contains
``.pre-commit-config.yaml`` and ``.githooks/pre-commit``, which run
``act -j test --matrix php:8.3 --matrix database:pgsql --matrix moodle-branch:MOODLE_405_STABLE``
before every commit. It is enabled locally with ``git config core.hooksPath .githooks``.

CI/CD
-----

``.github/workflows/ci.yml`` — **Moodle Plugin CI**
    Runs on every ``push``/``pull_request`` to ``main`` and ``MOODLE_*`` branches. Uses
    ``moodlehq/moodle-plugin-ci`` across a matrix of Moodle version (``MOODLE_405_STABLE``,
    ``MOODLE_500_STABLE``, ``MOODLE_501_STABLE``) × PHP (``8.1`` to ``8.4``) × database
    (``pgsql``, ``mariadb``), with some combinations excluded due to version incompatibility.
    Steps: PHP Lint, PHP Copy/Paste Detector (non-blocking), PHP Mess Detector (non-blocking),
    Moodle Code Checker (PHPCS), Moodle PHPDoc Checker (non-blocking), ``validate``,
    ``savepoints``, Mustache Lint (non-blocking), Grunt (non-blocking), PHPUnit
    (``--fail-on-warning``) and Behat with Chrome (non-blocking).

``.github/workflows/release.yml`` — **Release**
    Triggered by pushing any Git tag (``git tag -a 4.5.XXX -m "..."; git push origin
    4.5.XXX``). Validates the version consistency described above, packages an installable ZIP
    (``tool_painelava-<version>.zip``, excluding ``.git``, ``.github``, ``node_modules``,
    ``.gitignore``, ``tests`` and ``vendor``) and publishes a GitHub Release with
    automatically generated notes. The ZIP can be installed directly via **Site administration
    → Plugins → Install plugins**.

``.github/workflows/docs.yml`` — **Build & Deploy Documentation**
    Publishes this documentation (Sphinx) to GitHub Pages on every *push* to ``main`` that
    changes ``docs/**``. See :ref:`documentation` below.

.. _documentation:

Documentation
-------------

This documentation uses `Sphinx <https://www.sphinx-doc.org/>`_ with the
`moodle-docs-theme <https://pypi.org/project/moodle-docs-theme/>`_ theme and ``.rst`` files in
``docs/pt-br/`` (Portuguese) and ``docs/en/`` (English, this language, a complete translation).
To build locally:

.. code-block:: bash

   pip install sphinx moodle-docs-theme
   sphinx-build -W -b html docs/pt-br docs/_build/html/pt-br
   sphinx-build -W -b html docs/en docs/_build/html/en

The ``docs.yml`` workflow runs the same commands in CI (for both languages) and publishes the
result via ``actions/deploy-pages``.

Tests
---------

``tests/external/external_test.php`` is the repository's only test file — it covers only the
``tool_painelava_get_user_courses`` web service function (see :doc:`api-webservice`). None of
the HTTP endpoints in ``api/`` (v1 or v2) have automated test coverage. Local run (from the
Moodle root):

.. code-block:: bash

   vendor/bin/phpunit admin/tool/painelava/tests/external/external_test.php

Manual packaging
-----------------------

The release workflow automates packaging, but the same result can be reproduced locally:
copy the repository's contents into a folder named after the component without its prefix
(``painelava``), excluding ``.git``, ``.github``, ``node_modules``, ``.gitignore``, ``tests``
and ``vendor``, and compress that folder into ``tool_painelava-<version>.zip``.
