# docs/conf.py
import os
import sys

import moodle_docs_theme

sys.path.insert(0, os.path.abspath(".."))

project = "tool_painelava"

extensions = [
    "sphinx.ext.githubpages",
    "moodle_docs_theme",
]

templates_path = ["_templates"]
exclude_patterns = ["_build", "Thumbs.db", ".DS_Store"]

root_doc = "index"

html_theme = "moodle_docs_theme"
html_theme_path = [moodle_docs_theme.get_html_theme_path()]
html_static_path = ["_static"]

html_theme_options = {
    "project_name": "tool_painelava",
    "tagline": "Admin tool do Moodle que integra o Painel AVA a cursos, matrículas e notificações",
    "github_url": "https://github.com/suap-ava-suite/moodle-tool_painelava",
    "github_repo": "suap-ava-suite/moodle-tool_painelava",
    "github_version": "main",
    "doc_path": "docs/",
    "show_edit_on_github": True,
    "enable_dark_mode": True,
    "navigation_links": (
        "Início|index, Visão geral|visao-geral, Instalação|instalacao, "
        "API de serviço web|api-webservice, API HTTP|api-http, "
        "Dados e campos personalizados|dados-personalizados, Desenvolvimento|desenvolvimento"
    ),
}
