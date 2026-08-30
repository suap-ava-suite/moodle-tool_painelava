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

``tool_painelava`` é um plugin do tipo *Admin tool* para o Moodle que integra a plataforma ao
**Painel AVA**, usado no Instituto Federal do Rio Grande do Norte (IFRN). Ele expõe os cursos
de um usuário organizados por tipo (Diário, FIC, Coordenação, Laboratório, Modelo, Outros) via
uma função de serviço web nativa do Moodle, além de um conjunto de endpoints HTTP próprios
(autenticados por token) usados pelo Painel AVA para matricular, suspender matrícula, consultar
progresso e sincronizar preferências de usuário.

Conteúdo
--------

.. toctree::
   :maxdepth: 2

   visao-geral
   instalacao
   api-webservice
   api-http
   dados-personalizados
   desenvolvimento
