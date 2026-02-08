# Deploying to all-inkl.com (static webspace)

This project is a static site. You can upload the three files directly:

- `index.html`
- `styles.css`
- `app.js`

## Option A: Upload via FTP (recommended)

1. Log in to your all-inkl.com KAS (https://kas.all-inkl.com/).
2. Open **FTP** and create or note your FTP credentials.
3. Connect with an FTP client (e.g., FileZilla) using:
   - **Host**: `ftp.<your-domain>` (or the host listed in KAS)
   - **User**: your FTP username
   - **Password**: your FTP password
4. Open the web root (usually `htdocs/` or the domain folder).
5. Upload `index.html`, `styles.css`, and `app.js`.
6. Visit `https://<your-domain>/` to verify the editor loads.

## Option B: Upload via SFTP (if enabled)

1. In KAS, check whether SFTP/SSH is enabled for your account.
2. Use your SFTP credentials to connect and upload the same three files to `htdocs/`.

## Updating later

Re-upload the three files after making changes. The site is instantly updated.
