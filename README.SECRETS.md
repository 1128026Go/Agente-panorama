Cómo usar los ejemplos de entorno y evitar subir secretos

1) Nunca subas tu `.env` ni `n8n/.env` al repo. Usa los archivos `.env.example` como plantilla.

2) Instalación local de pre-commit y detect-secrets (recomendado):

- Requisitos: Python 3.8+ y pip.

- Instalar herramientas globalmente (una vez por máquina):

```powershell
pip install pre-commit detect-secrets
pre-commit install
```

- Generar la baseline (una vez en el repo, localmente):

```powershell
# detect-secrets wizard crea .secrets.baseline interactivo
detect-secrets scan > .secrets.baseline
```

- Para comprobar antes de un commit:

```powershell
pre-commit run --all-files
```

3) Si detectas que un secreto ya fue empujado previamente, rota la clave y limpia el historial con BFG o git-filter-repo.

4) Añade variables sensibles a tu gestor de secretos (GitHub Secrets, Azure Key Vault, etc.) y no a `.env` versionado.
