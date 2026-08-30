Visão geral
===========

O que o plugin faz
-------------------

``tool_painelava`` é um plugin *Admin tool* (instalado em ``admin/tool/painelava``) que serve
de ponte entre o Moodle e o **Painel AVA** (uma aplicação externa, referenciada no código como
"o painel Django"). Ele oferece duas superfícies de integração bem distintas:

* uma **função de serviço web nativa do Moodle** (``tool_painelava_get_user_courses``),
  registrada no subsistema de *web services* do core e protegida pelo mecanismo de capacidades
  do Moodle — ver :doc:`api-webservice`;
* um conjunto de **endpoints HTTP próprios**, fora do subsistema de *web services*, autenticados
  por um token simples enviado em um cabeçalho HTTP, usados para operações que a API de serviço
  web não cobre (matrícula, suspensão, progresso, preferências, favoritos, visibilidade de
  curso etc.) — ver :doc:`api-http`.

Além disso, o plugin cria automaticamente campos personalizados de curso (categoria **Painel
AVA**) usados para classificar cursos e controlar autoinscrição, registra uma tabela própria de
log de chamadas (``tool_painelava_logging``) e disponibiliza uma tarefa agendada — ver
:doc:`dados-personalizados`.

Requisitos
----------

* Moodle 4.5.0+ (``$plugin->requires`` = ``2024100710`` em ``version.php``).
* A esteira de CI (``.github/workflows/ci.yml``) testa o plugin contra
  ``MOODLE_405_STABLE``, ``MOODLE_500_STABLE`` e ``MOODLE_501_STABLE``, com PHP 8.1 a 8.4
  (algumas combinações excluídas por incompatibilidade — PHP 8.4 não é testado com Moodle 4.5,
  PHP 8.1 não é testado com Moodle 5.0+).

.. warning::
   O ``README.md`` do repositório afirma que os requisitos são "Moodle 4.0 ou superior
   (``requires = 2022041900``)" e "PHP 7.4+". Esses valores estão desatualizados: o
   ``version.php`` atual define ``requires = 2024100710`` (Moodle 4.5) e a matriz de CI só
   testa PHP 8.1 em diante. Esta documentação segue o que está de fato no código.

Dependências implícitas (opcionais)
--------------------------------------

O plugin não declara dependências formais em ``version.php``, mas parte do código só tem efeito
completo se o plugin ``local_suap`` estiver instalado:

* ``api/enrol_course.php`` usa ``get_config('local_suap', 'default_auth')`` (com *fallback*
  para ``manual``) e ``get_config('local_suap', 'default_user_preferences')`` ao criar um
  usuário sob demanda (*JIT provisioning* — ver :doc:`api-http`).
* ``classes/group_helper.php`` lê o campo de perfil ``campus_sigla``, que é criado pelo plugin
  ``auth_suap`` (não por ``tool_painelava``) — se esse campo não existir, o agrupamento por
  campus simplesmente cai no valor padrão ``SEM_CAMPUS``.

Estrutura do repositório
-------------------------

.. code-block:: text

   tool_painelava/
   ├── adminlib.php                        # Classe da página de configurações admin
   ├── settings.php                        # Registro da página de configurações
   ├── locallib.php                        # Helpers genéricos (config, aget, get_or_create...)
   ├── version.php                         # Versão/release/maturidade do plugin
   ├── api/                                 # Endpoints HTTP próprios (autenticação por token)
   │   ├── index.php                        # Dispatcher v1 (whitelist de serviços)
   │   ├── servicelib.php                   # Classe base tool_painelava\service
   │   ├── get_diarios.php                  # Timeline de cursos do usuário, com filtros/abas
   │   ├── get_progresso.php                # Percentual de conclusão por curso
   │   ├── get_atualizacoes_counts.php      # Contagem de mensagens/notificações não lidas
   │   ├── get_course_info.php              # Detalhes, docentes e carga horária de um curso
   │   ├── set_favourite_course.php         # Marca/desmarca curso como favorito
   │   ├── set_visible_course.php           # Altera a visibilidade de um curso
   │   ├── set_user_preference.php          # Grava uma preferência de usuário
   │   ├── sync_user_preference.php         # Repassa uma preferência para o Painel AVA (Django)
   │   ├── enrol_course.php                 # Matricula (ou cria e matricula) um usuário
   │   ├── suspend_enrol.php                # Suspende a matrícula de um usuário
   │   └── v2/                              # Dispatcher v2 (endpoints ainda placeholder)
   │       ├── index.php                    # Dispatcher v2 (whitelist própria)
   │       ├── get_conversas.php, get_notificacoes.php, get_salas.php
   │       ├── patch_conversa.php, patch_notificacao.php
   │       └── token_refresh.php, token_revoke.php
   ├── classes/
   │   ├── external/get_user_courses.php    # Implementação da função de serviço web
   │   ├── event/user_courses_requested.php # Evento disparado pela função de serviço web
   │   ├── task/sync_courses.php            # Tarefa agendada (placeholder)
   │   └── group_helper.php                 # Agrupamento automático por campus em autoinscrição
   ├── db/
   │   ├── access.php                       # Capacidades (tool/painelava:view, :viewothercourses)
   │   ├── services.php                     # Registro da função de serviço web
   │   ├── tasks.php                        # Registro da tarefa agendada
   │   ├── events.php                       # Observadores de eventos (vazio atualmente)
   │   ├── install.php / upgrade.php        # Ambos delegam para migrate.php
   │   ├── migrate.php                      # Cria tabela de log e campos personalizados de curso
   │   ├── install.xml                      # Schema da tabela tool_painelava_logging
   │   └── uninstall.php                    # Remove configurações do plugin
   ├── lang/{en,pt_br}/tool_painelava.php   # Strings de idioma
   ├── tests/external/external_test.php     # Testes PHPUnit da função de serviço web
   ├── requests.http                        # Exemplos de chamada aos endpoints HTTP (api/index.php)
   ├── test_debug.php                       # Script de depuração via CLI (não é parte da API)
   ├── docs/                                 # Esta documentação (Sphinx)
   └── .github/workflows/
       ├── ci.yml                            # moodle-plugin-ci (lint, PHPCS, PHPUnit, Behat...)
       ├── release.yml                       # Gera ZIP instalável em cada tag
       └── docs.yml                          # Publica esta documentação no GitHub Pages

.. note::
   ``test_debug.php``, na raiz do repositório, é um script de depuração executado via CLI
   (``CLI_SCRIPT``) para inspecionar manualmente a timeline de cursos de um usuário. Ele não é
   registrado em nenhum lugar do plugin (não aparece em ``db/services.php`` nem em
   ``api/index.php``) e não faz parte da superfície documentada da API — trata-se apenas de uma
   ferramenta de apoio ao desenvolvimento.

Organização
-----------

O repositório vive na organização `suap-ava-suite <https://github.com/suap-ava-suite>`_ como
``moodle-tool_painelava``, ao lado de outros componentes da suíte AVA/SUAP usados pelo IFRN
(por exemplo, ``auth_suap``, referenciado acima como origem do campo de perfil
``campus_sigla``).
