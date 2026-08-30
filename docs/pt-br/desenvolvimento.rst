Desenvolvimento
================

Ambiente de desenvolvimento
--------------------------------

Conforme ``GEMINI.md`` (raiz do repositório):

* *Backend*: Moodle.
* Banco de dados: PostgreSQL.
* O projeto usa ``sas`` como atalho para ``docker compose``.
* Para executar código dentro do container: ``sas exec moodle``.
* ``python`` é aceito como comando (por exemplo, para rodar scripts auxiliares dentro do
  container).

.. note::
   Não há ``AGENTS.md`` nem ``CLAUDE.md`` neste repositório — apenas ``GEMINI.md``, com o
   conteúdo resumido acima. Ele não define regras de versionamento ou de mensagens de commit;
   essas regras, quando existentes, foram inferidas abaixo a partir do que o CI efetivamente
   valida (``moodle-plugin-ci savepoints`` e ``release.yml``).

Versionamento
-------------

``.github/workflows/ci.yml`` roda ``moodle-plugin-ci savepoints``, que verifica a
consistência entre alterações em ``db/`` (schema, *upgrade steps*) e o incremento de
``$plugin->version`` em ``version.php``. Como convenção observada no arquivo atual:

* ``$plugin->version`` segue o padrão ``YYYY_MM_DD_XXX``.
* ``$plugin->release`` segue o padrão ``4.5.XXX``.
* ``XXX`` é o mesmo valor nos dois campos.

``.github/workflows/release.yml`` reforça essa regra no momento do *release*: valida que os 3
últimos dígitos de ``$plugin->version`` batem com o sufixo de ``$plugin->release``, e que
``$plugin->release`` corresponde exatamente ao nome da tag Git usada para disparar o workflow.

.. note::
   Alterações **apenas** em ``docs/`` (como as desta documentação) não tocam ``db/`` nem
   ``lang/`` e, portanto, não exigem incremento de ``version.php`` — nem ``savepoints`` nem
   ``release.yml`` avaliam o conteúdo de ``docs/``.

Pre-commit
-------------

Assim como outros plugins da suíte (por exemplo, ``auth_suap``), este repositório contém
``.pre-commit-config.yaml`` e ``.githooks/pre-commit``, que rodam
``act -j test --matrix php:8.3 --matrix database:pgsql --matrix moodle-branch:MOODLE_405_STABLE``
antes de cada commit. A ativação é feita localmente com
``git config core.hooksPath .githooks``.

CI/CD
-----

``.github/workflows/ci.yml`` — **Moodle Plugin CI**
    Executa em todo ``push``/``pull_request`` para ``main`` e branches ``MOODLE_*``. Usa
    ``moodlehq/moodle-plugin-ci`` em uma matriz de Moodle (``MOODLE_405_STABLE``,
    ``MOODLE_500_STABLE``, ``MOODLE_501_STABLE``) × PHP (``8.1`` a ``8.4``) × banco (``pgsql``,
    ``mariadb``), com algumas combinações excluídas por incompatibilidade de versão. Etapas:
    PHP Lint, PHP Copy/Paste Detector (não bloqueante), PHP Mess Detector (não bloqueante),
    Moodle Code Checker (PHPCS), Moodle PHPDoc Checker (não bloqueante), ``validate``,
    ``savepoints``, Mustache Lint (não bloqueante), Grunt (não bloqueante), PHPUnit
    (``--fail-on-warning``) e Behat com Chrome (não bloqueante).

``.github/workflows/release.yml`` — **Release**
    Disparado por *push* de qualquer tag Git (``git tag -a 4.5.XXX -m "..."; git push origin
    4.5.XXX``). Valida a consistência de versão descrita acima, empacota um ZIP instalável
    (``tool_painelava-<version>.zip``, excluindo ``.git``, ``.github``, ``node_modules``,
    ``.gitignore``, ``tests`` e ``vendor``) e publica uma GitHub Release com notas geradas
    automaticamente. O ZIP pode ser instalado diretamente em **Administração do site → Plugins
    → Instalar plugins**.

``.github/workflows/docs.yml`` — **Build & Deploy Documentation**
    Publica esta documentação (Sphinx) no GitHub Pages a cada *push* em ``main`` que altere
    ``docs/**``. Veja :ref:`documentacao` abaixo.

.. _documentacao:

Documentação
------------

Esta documentação usa `Sphinx <https://www.sphinx-doc.org/>`_ com o tema
`moodle-docs-theme <https://pypi.org/project/moodle-docs-theme/>`_ e arquivos ``.rst`` em
``docs/pt-br/`` (português, este idioma) e ``docs/en/`` (inglês, tradução completa). Para gerar
localmente:

.. code-block:: bash

   pip install sphinx moodle-docs-theme
   sphinx-build -W -b html docs/pt-br docs/_build/html/pt-br
   sphinx-build -W -b html docs/en docs/_build/html/en

O workflow ``docs.yml`` roda os mesmos comandos em CI (para os dois idiomas) e publica o
resultado via ``actions/deploy-pages``.

Testes
---------

``tests/external/external_test.php`` é o único arquivo de teste do repositório — cobre apenas
a função de serviço web ``tool_painelava_get_user_courses`` (ver :doc:`api-webservice`).
Nenhum dos endpoints HTTP em ``api/`` (v1 ou v2) tem cobertura de testes automatizados.
Execução local (a partir da raiz do Moodle):

.. code-block:: bash

   vendor/bin/phpunit admin/tool/painelava/tests/external/external_test.php

Empacotamento manual
-----------------------

O workflow de release automatiza o empacotamento, mas o mesmo resultado pode ser reproduzido
localmente: copiar o conteúdo do repositório para uma pasta com o nome do componente sem o
prefixo (``painelava``), excluindo ``.git``, ``.github``, ``node_modules``, ``.gitignore``,
``tests`` e ``vendor``, e compactar essa pasta em ``tool_painelava-<version>.zip``.
