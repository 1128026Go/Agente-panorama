# n8n local deployment notes

Important instructions to preserve n8n data and keep the instance stable.

1) Never delete or rename the `n8n_data` folder

- This folder is mounted as a Docker volume at `/home/node/.n8n` and contains all workflows,
  credentials and the instance settings including the `config` file with the `encryptionKey`.
- Do not run `docker compose down -v` on this stack (that removes volumes). Use `docker compose down` without `-v`.

2) Keep a single, stable `N8N_ENCRYPTION_KEY`

- The key stored in `n8n/n8n_data/config` must match the value of `N8N_ENCRYPTION_KEY` used by the container.
- If they mismatch, n8n refuses to start to avoid decrypting data with the wrong key.
- If you ever rotate the key, first export and backup workflows/credentials (see Backup section), then rotate.

3) Basic auth / admin user

- `n8n/.env` contains current admin credentials (N8N_BASIC_AUTH_USER / N8N_BASIC_AUTH_PASSWORD). Keep them safe.
- For personal usage an initial admin user is enough. For teams use a proper identity provider.

4) Quick backup commands (PowerShell)

Run these from the repository root to backup the entire `n8n_data` folder safely:

```powershell
$dt = (Get-Date).ToString('yyyyMMdd_HHmmss')
Compress-Archive -Path .\n8n\n8n_data -DestinationPath .\n8n\n8n_data_backup_$dt.zip -Force
```

5) Restore note

- To restore, stop the container, extract the backup to `n8n/n8n_data` and start the container again.
- Make sure permissions are correct for your OS so the container can read/write the files.

6) Tools included

- `check_n8n_key.ps1` — compares configured key vs key in `n8n_data/config` and warns if different.
- `backup_n8n.ps1` — creates a timestamped zip backup of `n8n/n8n_data`.

If you want, I can add automated CI checks that prevent accidental deletion of `n8n_data` or key changes, but the steps above are low-risk and effective for local development.
