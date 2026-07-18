<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class SystemUpdateService
{
    public function localVersion(): string
    {
        $path = base_path('VERSION');
        if (! is_readable($path)) {
            return '0.0.0';
        }

        return $this->normalizeVersion((string) file_get_contents($path));
    }

    public function remoteVersion(): ?string
    {
        $repo = (string) config('services.github.repo', 'patryckMichel/lets-bet');
        $branch = (string) config('services.github.branch', 'main');
        $token = config('services.github.token');
        $versionPath = (string) config('services.github.version_path', 'platform/VERSION');

        // Público: raw.githubusercontent.com
        $rawUrl = "https://raw.githubusercontent.com/{$repo}/{$branch}/{$versionPath}";

        try {
            $request = Http::timeout(12)->accept('text/plain');
            if (filled($token)) {
                $request = $request->withToken((string) $token);
            }

            $response = $request->get($rawUrl);
            if ($response->successful()) {
                return $this->normalizeVersion($response->body());
            }

            // Fallback API (repo privado)
            if (filled($token)) {
                $api = Http::timeout(12)
                    ->withToken((string) $token)
                    ->accept('application/vnd.github.raw')
                    ->get("https://api.github.com/repos/{$repo}/contents/{$versionPath}", [
                        'ref' => $branch,
                    ]);

                if ($api->successful()) {
                    return $this->normalizeVersion($api->body());
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
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
     * @return array{ok: bool, log: string, local: string, remote: ?string}
     */
    public function status(): array
    {
        $local = $this->localVersion();
        $remote = $this->remoteVersion();

        return [
            'local' => $local,
            'remote' => $remote,
            'update_available' => $remote !== null && version_compare($remote, $local, '>'),
            'commit' => $this->localGitCommit(),
            'script_ok' => $this->scriptConfigured(),
            'repo' => (string) config('services.github.repo', 'patryckMichel/lets-bet'),
            'branch' => (string) config('services.github.branch', 'main'),
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

    protected function normalizeVersion(string $raw): string
    {
        $v = trim(preg_replace('/\s+/', '', $raw) ?? '');
        $v = ltrim($v, 'vV');

        if ($v === '' || ! preg_match('/^\d+(\.\d+){0,3}$/', $v)) {
            return '0.0.0';
        }

        return $v;
    }
}
