<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class SystemUpdateService
{
    protected ?string $lastRemoteError = null;

    public function localVersion(): string
    {
        $candidates = [
            base_path('VERSION'),
            base_path('platform/VERSION'),
        ];

        foreach ($candidates as $path) {
            if (is_readable($path)) {
                return $this->normalizeVersion((string) file_get_contents($path));
            }
        }

        return '0.0.0';
    }

    public function remoteVersion(): ?string
    {
        $this->lastRemoteError = null;

        $repo = (string) config('services.github.repo', 'patryckMichel/lets-bet');
        $branch = (string) config('services.github.branch', 'main');
        $token = config('services.github.token');
        $versionPath = ltrim((string) config('services.github.version_path', 'platform/VERSION'), '/');
        $errors = [];

        // 1) GitHub Contents API primeiro (evita cache velho do raw.githubusercontent.com)
        $apiUrl = "https://api.github.com/repos/{$repo}/contents/{$versionPath}";
        try {
            $request = Http::timeout(15)
                ->withHeaders($this->githubHeaders())
                ->accept('application/vnd.github.raw');

            if (filled($token)) {
                $request = $request->withToken((string) $token);
            }

            $api = $request->get($apiUrl, [
                'ref' => $branch,
            ]);
            if ($api->successful()) {
                $version = $this->normalizeVersion($api->body());
                if ($version !== '0.0.0') {
                    $this->lastRemoteError = null;

                    return $version;
                }
                $errors[] = 'API GitHub retornou conteúdo inválido';
            } else {
                $errors[] = 'API GitHub HTTP '.$api->status();
            }
        } catch (\Throwable $e) {
            $errors[] = 'API GitHub: '.$e->getMessage();
        }

        // 2) Fallback: tip do branch (SHA) + raw com commit (sem CDN stale de /main/)
        try {
            $refReq = Http::timeout(15)->withHeaders($this->githubHeaders());
            if (filled($token)) {
                $refReq = $refReq->withToken((string) $token);
            }
            $ref = $refReq->get("https://api.github.com/repos/{$repo}/git/ref/heads/{$branch}");
            if ($ref->successful()) {
                $sha = (string) data_get($ref->json(), 'object.sha', '');
                if ($sha !== '') {
                    $rawCommitUrl = "https://raw.githubusercontent.com/{$repo}/{$sha}/{$versionPath}";
                    $parsed = $this->fetchVersionText($rawCommitUrl, filled($token) ? (string) $token : null);
                    if ($parsed !== null) {
                        return $parsed;
                    }
                    $errors[] = $this->lastRemoteError ?: ('falha: '.$rawCommitUrl);
                }
            } else {
                $errors[] = 'Ref GitHub HTTP '.$ref->status();
            }
        } catch (\Throwable $e) {
            $errors[] = 'Ref GitHub: '.$e->getMessage();
        }

        // 3) Último recurso: raw da branch com cache-buster
        $bust = (string) time();
        $urls = [
            "https://raw.githubusercontent.com/{$repo}/{$branch}/{$versionPath}?t={$bust}",
        ];

        foreach ($urls as $url) {
            $parsed = $this->fetchVersionText($url, filled($token) ? (string) $token : null);
            if ($parsed !== null) {
                return $parsed;
            }
            $errors[] = $this->lastRemoteError ?: ('falha: '.$url);
        }

        $this->lastRemoteError = implode(' | ', array_slice($errors, 0, 3));

        return null;
    }

    public function lastRemoteError(): ?string
    {
        return $this->lastRemoteError;
    }

    public function isUpdateAvailable(): bool
    {
        $remote = $this->remoteVersion();
        if ($remote === null) {
            return false;
        }

        return version_compare($remote, $this->localVersion(), '>');
    }

    public function localGitCommit(): ?string
    {
        $result = Process::path(base_path())
            ->timeout(5)
            ->run(['git', 'rev-parse', '--short', 'HEAD']);

        if (! $result->successful()) {
            // Monorepo: Laravel em /platform, git root um nível acima
            $result = Process::path(dirname(base_path()))
                ->timeout(5)
                ->run(['git', 'rev-parse', '--short', 'HEAD']);
        }

        if (! $result->successful()) {
            return null;
        }

        return trim($result->output()) ?: null;
    }

    public function scriptConfigured(): bool
    {
        $script = (string) config('services.update.script_path', '/usr/local/bin/lestbet-update.sh');

        return $script !== '' && is_file($script) && is_executable($script);
    }

    /**
     * @return array{
     *   local: string,
     *   remote: ?string,
     *   update_available: bool,
     *   commit: ?string,
     *   script_ok: bool,
     *   repo: string,
     *   branch: string,
     *   version_path: string,
     *   remote_error: ?string,
     *   remote_url: string
     * }
     */
    public function status(): array
    {
        $local = $this->localVersion();
        $remote = $this->remoteVersion();
        $repo = (string) config('services.github.repo', 'patryckMichel/lets-bet');
        $branch = (string) config('services.github.branch', 'main');
        $versionPath = ltrim((string) config('services.github.version_path', 'platform/VERSION'), '/');

        return [
            'local' => $local,
            'remote' => $remote,
            'update_available' => $remote !== null && version_compare($remote, $local, '>'),
            'commit' => $this->localGitCommit(),
            'script_ok' => $this->scriptConfigured(),
            'repo' => $repo,
            'branch' => $branch,
            'version_path' => $versionPath,
            'remote_error' => $this->lastRemoteError,
            'remote_url' => "https://raw.githubusercontent.com/{$repo}/{$branch}/{$versionPath}",
        ];
    }

    /**
     * @return array{ok: bool, log: string}
     */
    public function runUpdate(): array
    {
        if (! $this->isUpdateAvailable()) {
            throw new RuntimeException('Nenhuma atualização disponível. A versão local já está em dia.');
        }

        $script = (string) config('services.update.script_path', '/usr/local/bin/lestbet-update.sh');
        if ($script === '' || ! is_file($script)) {
            throw new RuntimeException(
                'Script de update não encontrado ('.$script.'). Configure a VPS com scripts/vps-enable-git-updates.sh.'
            );
        }

        $result = Process::timeout(180)
            ->run(['sudo', '-n', $script]);

        $log = trim($result->output()."\n".$result->errorOutput());

        return [
            'ok' => $result->successful(),
            'log' => $log !== '' ? $log : ($result->successful() ? 'Update concluído.' : 'Falha sem saída do script.'),
        ];
    }

    protected function fetchVersionText(string $url, ?string $token): ?string
    {
        try {
            $request = Http::timeout(15)
                ->withHeaders($this->githubHeaders())
                ->accept('text/plain');

            if ($token !== null && $token !== '') {
                $request = $request->withToken($token);
            }

            $response = $request->get($url);
            if (! $response->successful()) {
                $this->lastRemoteError = 'HTTP '.$response->status().' em '.$url;

                return null;
            }

            $version = $this->normalizeVersion($response->body());
            if ($version === '0.0.0') {
                $this->lastRemoteError = 'Conteúdo inválido em '.$url;

                return null;
            }

            $this->lastRemoteError = null;

            return $version;
        } catch (\Throwable $e) {
            $this->lastRemoteError = $e->getMessage().' ('.$url.')';

            return null;
        }
    }

    /** @return array<string, string> */
    protected function githubHeaders(): array
    {
        return [
            'User-Agent' => 'LESTBET-SystemUpdate/1.0',
            'Accept' => 'text/plain',
        ];
    }

    protected function normalizeVersion(string $raw): string
    {
        // Remove BOM e espaços/quebras
        $v = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $v = trim(preg_replace('/\s+/', '', $v) ?? '');
        $v = ltrim($v, 'vV');

        if ($v === '' || ! preg_match('/^\d+(\.\d+){0,3}$/', $v)) {
            return '0.0.0';
        }

        return $v;
    }
}
