API de serviço web
====================

``tool_painelava`` registra uma única função no subsistema nativo de *web services* do Moodle:
``tool_painelava_get_user_courses``, implementada em
``classes/external/get_user_courses.php`` e registrada em ``db/services.php``.

Registro do serviço
-----------------------

.. list-table::
   :header-rows: 1
   :widths: 30 70

   * - Propriedade
     - Valor
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
     - ``MOODLE_OFFICIAL_MOBILE_SERVICE`` (o serviço oficial do app móvel do Moodle)

Parâmetros
-------------

.. list-table::
   :header-rows: 1
   :widths: 20 15 15 50

   * - Parâmetro
     - Tipo
     - Padrão
     - Descrição
   * - ``userid``
     - ``PARAM_INT``
     - ``0``
     - ID do usuário alvo. ``0`` significa o usuário autenticado (``$USER->id``).

Permissões
-------------

* Um usuário sempre pode consultar os próprios cursos (``userid == $USER->id``, ou ``userid =
  0``).
* Para consultar cursos de **outro** usuário é exigida a capacidade
  ``tool/painelava:viewothercourses`` no contexto de sistema (``require_capability``) — por
  padrão concedida apenas ao papel ``manager``.
* O usuário alvo precisa existir e não estar marcado como excluído (``deleted = 0``); caso
  contrário a função lança ``moodle_exception`` com o código de erro ``invaliduser``.

Classificação dos cursos
----------------------------

Para cada curso em que o usuário está matriculado (via ``enrol_get_users_courses()``), o tipo é
resolvido por ``resolve_course_type()`` na seguinte ordem de prioridade:

1. **Campo personalizado de curso** cujo *shortname* seja igual a
   ``get_config('tool_painelava', 'coursetypefield')`` — se ausente, cai para o valor padrão
   embutido no código ``'tipo_curso'``. O valor do campo é normalizado (minúsculas, acentos
   tratados) por ``normalise_type_value()`` para um dos tipos conhecidos.
2. Caso o campo não determine o tipo, o *shortname* do curso é comparado (``stripos``, prefixo)
   contra os prefixos configuráveis ``prefix_fic`` (padrão ``FIC-``), ``prefix_coordenacao``
   (padrão ``COORD-``), ``prefix_laboratorio`` (padrão ``LAB-``) e ``prefix_modelo`` (padrão
   ``MODELO-``); depois, contra ``prefix_diario`` (padrão vazio — portanto normalmente inativo).
3. Se nada bater, o tipo cai em ``outros``.

.. warning::
   Nenhuma dessas chaves de configuração (``coursetypefield``, ``prefix_fic``,
   ``prefix_coordenacao``, ``prefix_laboratorio``, ``prefix_modelo``, ``prefix_diario``) tem
   campo correspondente em ``settings.php`` — ver o aviso em :doc:`instalacao`. Na prática, sem
   edição manual da tabela ``config_plugins``, a classificação depende inteiramente dos valores
   padrão embutidos no código e dos testes em ``tests/external/external_test.php`` cobrem
   exatamente esses padrões (prefixos ``FIC-``, ``COORD-``, ``LAB-``, ``MODELO-``).

Estrutura de retorno
------------------------

A resposta é um objeto com seis listas, uma por tipo de curso reconhecido:

.. code-block:: text

   {
     "diario": [...], "fic": [...], "coordenacao": [...],
     "laboratorio": [...], "modelo": [...], "outros": [...]
   }

Cada item de curso segue esta estrutura:

.. list-table::
   :header-rows: 1
   :widths: 25 20 55

   * - Campo
     - Tipo
     - Descrição
   * - ``id``, ``shortname``, ``fullname``, ``idnumber``, ``summary``, ``summaryformat``,
       ``startdate``, ``enddate``, ``visible``, ``category``
     - —
     - Campos padrão do registro de curso do Moodle.
   * - ``course_type``
     - ``string``
     - Um de ``diario``, ``fic``, ``coordenacao``, ``laboratorio``, ``modelo``, ``outros``.
   * - ``role``
     - ``string``
     - *Shortname* do primeiro papel retornado por ``get_user_roles()`` para o usuário nesse
       curso (papel "principal", na ordem em que o Moodle os retorna).
   * - ``roles``
     - lista de objetos
     - Todos os papéis do usuário no curso: ``roleid``, ``shortname``, ``name`` (traduzido via
       ``role_get_name()``).
   * - ``customfields``
     - lista de objetos
     - Todos os campos personalizados de curso: ``shortname``, ``name``, ``type``, ``value``
       (valor formatado para exibição), ``valueraw`` (valor bruto armazenado).

Evento disparado
-------------------

Se a configuração ``enablelogging`` estiver habilitada (ver aviso acima sobre a ausência dessa
configuração na UI), cada chamada dispara o evento
``tool_painelava\event\user_courses_requested`` (``classes/event/user_courses_requested.php``),
registrado com ``crud = 'r'`` e associado ao usuário que fez a chamada e ao ``relateduserid``
(usuário alvo).

.. note::
   Este é o único evento definido pelo plugin. ``db/events.php`` registra a lista de
   *observers* que o plugin escuta de outros componentes — atualmente vazia
   (``$observers = [];``).

Testes
---------

``tests/external/external_test.php`` cobre, via ``externallib_advanced_testcase``: permissões
(usuário comum não pode ver curso de outro sem a capacidade; ``manager`` pode; usuário excluído
lança ``invaliduser``), classificação por prefixo de *shortname* para cada um dos quatro tipos
configuráveis, agregação de múltiplos cursos e a presença de todas as chaves esperadas na
estrutura de retorno. Execução local (a partir da raiz do Moodle):

.. code-block:: bash

   vendor/bin/phpunit admin/tool/painelava/tests/external/external_test.php
