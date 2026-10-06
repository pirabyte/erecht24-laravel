# Security Policy

Please do not open public issues that include private eRecht24 project API keys, legal text secrets, or account-specific integration details.

The bundled pirabyte developer key identifies this integration and is public by design.

`htmlOrLastKnownGood()` uses Symfony HTML Sanitizer before validating, retaining or serving legal text. `html()` and `document()` return raw provider HTML; callers must sanitize it before rendering.

Report vulnerabilities privately to security@pirabyte.io.

Supported versions start at the latest stable `1.x` release.


## CI trust boundary

CI uses ordinary `pull_request` events targeting `main` and pushes to `main`. Fork PRs run without repository or organization secrets and with a read-only workflow token. The workflow does not use `pull_request_target`, comment commands, shared caches, or artifacts from other workflows. It uses disposable GitHub-hosted runners, pinned Action commits, and a checkout that does not persist credentials.

Repository settings require approval for every external fork contributor, require full SHA pins, allow only the Action commits used by CI, and prevent workflow tokens from approving pull requests. These controls are configured in GitHub. Review the proposed workflow and executable code before approving an external run. Approval permits untrusted code execution; it does not make the contribution trusted.

Do not pass customer API keys, deployment credentials, or publishing tokens into test jobs. Never interpolate contributor-controlled titles, branch names, bodies, or comments into shell commands. Review workflow, dependency, install-script, and test changes before merging. A fork owner can modify and run their own copy without gaining access to this repository's credentials.
