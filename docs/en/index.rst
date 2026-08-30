tool_painelava
===============

.. image:: https://img.shields.io/badge/License-GPLv3-blue.svg
   :target: https://github.com/suap-ava-suite/moodle-tool_painelava/blob/main/LICENSE
   :alt: License

.. image:: https://github.com/suap-ava-suite/moodle-tool_painelava/actions/workflows/ci.yml/badge.svg
   :target: https://github.com/suap-ava-suite/moodle-tool_painelava/actions/workflows/ci.yml
   :alt: Moodle Plugin CI

.. image:: https://img.shields.io/github/v/release/suap-ava-suite/moodle-tool_painelava
   :target: https://github.com/suap-ava-suite/moodle-tool_painelava/releases
   :alt: Latest release

.. image:: https://img.shields.io/badge/Moodle-4.5.0%2B-orange.svg
   :target: https://github.com/suap-ava-suite/moodle-tool_painelava/blob/main/version.php
   :alt: Moodle compatibility

.. image:: https://img.shields.io/badge/PHP-8.1%20--%208.4-777bb4.svg
   :target: https://github.com/suap-ava-suite/moodle-tool_painelava/blob/main/.github/workflows/ci.yml
   :alt: PHP compatibility

.. image:: https://github.com/suap-ava-suite/moodle-tool_painelava/actions/workflows/docs.yml/badge.svg
   :target: https://github.com/suap-ava-suite/moodle-tool_painelava/actions/workflows/docs.yml
   :alt: Build & Deploy Documentation

``tool_painelava`` is an *Admin tool* type plugin for Moodle that integrates the platform with
the **Painel AVA**, used at the Federal Institute of Rio Grande do Norte (IFRN). It exposes a
user's courses organized by type (Diário, FIC, Coordenação, Laboratório, Modelo, Outros) via a
native Moodle web service function, plus a set of dedicated HTTP endpoints (token-authenticated)
used by the Painel AVA to enrol, suspend enrolment, check progress and sync user preferences.

Contents
--------

.. toctree::
   :maxdepth: 2

   visao-geral
   instalacao
   api-webservice
   api-http
   dados-personalizados
   desenvolvimento
