Instalação
==========

1. Instalação do plugin
--------------------------

1. Copie (ou clone) o conteúdo deste repositório para
   ``<moodle_root>/admin/tool/painelava/``.
2. Acesse **Administração do site** no Moodle — a atualização do banco de dados roda
   automaticamente (executa ``db/install.php``, que cria a tabela ``tool_painelava_logging`` e
   os campos personalizados de curso da categoria **Painel AVA** — ver
   :doc:`dados-personalizados`).

2. Configuração do plugin
----------------------------

Em **Administração do site → Plugins → Ferramentas de administração → Painel AVA**
(``admin/tool/painelava/settings.php``), configure:

.. list-table::
   :header-rows: 1
   :widths: 30 15 55

   * - Configuração
     - Chave interna
     - Descrição
   * - Token de autenticação
     - ``auth_token``
     - Token que o Painel AVA deve enviar no cabeçalho ``Authentication: Token <valor>`` para
       autenticar-se nos endpoints HTTP em ``api/`` — ver :doc:`api-http`.
   * - Painel AVA URL
     - ``painel_url``
     - Base da URL do Painel AVA (padrão ``https://ava.ifrn.edu.br``). Usada por
       ``api/sync_user_preference.php`` para repassar preferências de usuário ao Painel AVA.
   * - Campo personalizado do curso: Sala Tipo
     - ``course_custom_field_sala_tipo``
     - Descrito na tela de configuração como o campo usado para identificar o tipo de sala do
       curso (padrão ``sala_tipo``).

.. warning::
   ``settings.php``/``adminlib.php`` só expõem essas três configurações na interface admin.
   Boa parte do código em ``classes/external/get_user_courses.php`` lê, porém, configurações
   adicionais que **não existem em nenhuma tela** — ``coursetypefield``, ``enablelogging``,
   ``prefix_fic``, ``prefix_coordenacao``, ``prefix_laboratorio``, ``prefix_modelo`` e
   ``prefix_diario`` (ver :doc:`api-webservice`). Sem uma UI para defini-las, essas
   configurações só têm efeito se forem inseridas manualmente na tabela ``config_plugins`` do
   Moodle (``plugin = 'tool_painelava'``); do contrário, o código usa os valores padrão
   embutidos (por exemplo, ``coursetypefield`` cai para ``'tipo_curso'`` — observe que esse
   nome **não coincide** com a chave ``course_custom_field_sala_tipo`` exposta na tela, ou seja,
   a configuração visível na interface admin não é, de fato, a que a classificação por campo
   personalizado consulta).

3. Capacidades
-----------------

.. list-table::
   :header-rows: 1
   :widths: 35 15 50

   * - Capacidade
     - Papéis padrão
     - Finalidade
   * - ``tool/painelava:view``
     - ``manager``, ``coursecreator``
     - Acesso à área administrativa do Painel AVA no Moodle.
   * - ``tool/painelava:viewothercourses``
     - ``manager``
     - Permite consultar, via ``tool_painelava_get_user_courses``, os cursos de **outro**
       usuário (sem essa capacidade, um usuário só pode consultar os próprios cursos).

Conceda ``tool/painelava:viewothercourses`` apenas a papéis estritamente necessários — ver
também ``SECURITY.md`` no repositório.

4. Testando o acesso
------------------------

* A função de serviço web ``tool_painelava_get_user_courses`` fica disponível para qualquer
  serviço de webservices habilitado que a inclua (ela é registrada, entre outros, no serviço
  móvel oficial do Moodle — ver :doc:`api-webservice`).
* Os endpoints HTTP em ``api/`` exigem o cabeçalho ``Authentication: Token <auth_token
  configurado>`` em toda chamada — ver exemplo em ``requests.http`` e detalhes em
  :doc:`api-http`.

.. note::
   Diferente de plugins de autenticação (como ``auth_suap``), ``tool_painelava`` não define uma
   tela de login nem um fluxo OAuth2 — toda a integração acontece via chamadas HTTP feitas pelo
   Painel AVA (aplicação externa) ao Moodle.
