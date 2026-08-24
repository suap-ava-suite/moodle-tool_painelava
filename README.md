# Painel AVA – moodle-tool_painelava

> Moodle Admin Tool plugin que integra o Moodle ao **Painel AVA**, fornecendo
> uma API externa para recuperar os dados de cursos de um usuário organizados
> por tipo de curso.

Documentação: publicada em https://suap-ava-suite.github.io/moodle-tool_painelava/ (gerada
automaticamente a cada push em `docs/` via `.github/workflows/docs.yml`, usando o tema Sphinx
[moodle-docs-theme](https://pypi.org/project/moodle-docs-theme/)). Para gerar localmente:

```bash
pip install sphinx moodle-docs-theme
sphinx-build -W -b html docs docs/_build/html
```

Páginas: `docs/visao-geral.rst`, `docs/instalacao.rst`, `docs/api-webservice.rst`,
`docs/api-http.rst`, `docs/dados-personalizados.rst`, `docs/desenvolvimento.rst`.

---

## Funcionalidades

| Funcionalidade | Descrição |
|---|---|
| **API externa** | `tool_painelava_get_user_courses` – retorna todos os cursos em que o usuário está matriculado, separados por tipo (Diário, FIC, Coordenação, Laboratório, Modelo, Outros). |
| **Campos personalizados** | Todos os campos customizados de cada curso são retornados na resposta. |
| **Papéis** | O papel principal e todos os papéis do usuário em cada curso são retornados. |
| **Configurações** | Permite configurar o campo personalizado e os prefixos de nome curto usados para classificar os tipos de cursos. |
| **Log de eventos** | Opcionalmente registra cada chamada à API no log do Moodle. |
| **Tarefa agendada** | Tarefa de sincronização de dados disponível via interface de tarefas do Moodle. |

---

## Requisitos

- Moodle **4.0** ou superior (`requires = 2022041900`)
- PHP 7.4+

---

## Instalação

1. Copie (ou clone) o conteúdo deste repositório para
   `<moodle_root>/admin/tool/painelava/`.
2. Acesse o painel de administração do Moodle e execute a atualização do banco
   de dados.
3. Navegue até **Administração do site → Plugins → Ferramentas de administração
   → Painel AVA** para configurar o plugin.

---

## Configuração

1. `auth_token`: Token que o Painel AVA usará para autenticar-se no Moodle.

---

## API Externa

### Função: `tool_painelava_get_user_courses`

**Parâmetros**

| Parâmetro | Tipo | Padrão | Descrição |
|---|---|---|---|
| `userid` | `int` | `0` | ID do usuário. Use `0` para o usuário atual. |

**Retorno**

```json
{
  "diario":      [ /* lista de objetos de curso */ ],
  "fic":         [ /* ... */ ],
  "coordenacao": [ /* ... */ ],
  "laboratorio": [ /* ... */ ],
  "modelo":      [ /* ... */ ],
  "outros":      [ /* ... */ ]
}
```

Cada objeto de curso contém:

```json
{
  "id": 42,
  "shortname": "FIC-001",
  "fullname": "Curso FIC de Informática",
  "idnumber": "",
  "summary": "...",
  "summaryformat": 1,
  "startdate": 1704067200,
  "enddate": 0,
  "visible": 1,
  "category": 3,
  "course_type": "fic",
  "role": "student",
  "roles": [
    { "roleid": 5, "shortname": "student", "name": "Estudante" }
  ],
  "customfields": [
    {
      "shortname": "tipo_curso",
      "name": "Tipo de Curso",
      "type": "select",
      "value": "FIC",
      "valueraw": "fic"
    }
  ]
}
```

**Permissões**

- Um usuário pode sempre consultar seus próprios cursos.
- Para consultar cursos de outro usuário, é necessária a capacidade
  `tool/painelava:viewothercourses` no contexto do sistema (concedida por padrão
  ao papel `manager`).

---

## Testes

Os testes unitários estão em `tests/external_test.php` e utilizam o framework
PHPUnit integrado ao Moodle.

```bash
# Execute a partir do diretório raiz do Moodle
vendor/bin/phpunit admin/tool/painelava/tests/external_test.php
```

---

## Estrutura de Arquivos

```
admin/tool/painelava/
├── classes/
│   ├── event/
│   │   └── user_courses_requested.php   # Evento disparado pela API
│   ├── external/
│   │   └── get_user_courses.php         # Implementação da API externa
│   └── task/
│       └── sync_courses.php             # Tarefa agendada
├── db/
│   ├── access.php                       # Definição de capacidades
│   ├── events.php                       # Observadores de eventos
│   ├── install.php                      # Hook pós-instalação
│   ├── install.xml                      # Schema do banco de dados
│   ├── services.php                     # Registro da função externa
│   ├── tasks.php                        # Registro de tarefas agendadas
│   ├── uninstall.php                    # Hook de desinstalação
│   └── upgrade.php                      # Passos de atualização
├── lang/
│   ├── en/tool_painelava.php               # Strings em inglês
│   └── pt_br/tool_painelava.php            # Strings em português (Brasil)
├── pix/
│   └── icon.png                         # Ícone do plugin (48×48)
├── tests/
│   └── external_test.php                # Testes unitários PHPUnit
├── settings.php                         # Página de configurações admin
├── version.php                          # Metadados do plugin
└── README.md                            # Este arquivo
```

---

## Licença

GNU General Public License v3 or later –
<http://www.gnu.org/copyleft/gpl.html>

© 2024 IFRN
