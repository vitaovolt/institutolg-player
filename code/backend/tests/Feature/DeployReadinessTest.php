<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DeployReadinessTest extends TestCase
{
    use RefreshDatabase;
    public function test_health_inclui_check_de_database(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.checks.database', 'ok')
            ->assertJsonPath('data.service', 'institutolg-player-api');
    }

    public function test_env_example_tem_chaves_de_producao(): void
    {
        $env = File::get(base_path('.env.example'));

        foreach (['APP_URL=', 'FRONTEND_URL=', 'DB_CONNECTION=pgsql', 'QUEUE_CONNECTION=', 'BIBLIOTECA_AULAS_DRIVER=', 'AWS_ENDPOINT=', 'BIBLIOTECA_DRIVE_FOLDER_ID=', 'BIBLIOTECA_UPLOAD_MAX_BYTES='] as $needle) {
            $this->assertStringContainsString($needle, $env);
        }
    }

    public function test_guia_de_armazenamento_existe(): void
    {
        $root = dirname(base_path(), 2);

        $this->assertFileExists($root.'/docs/ARMAZENAMENTO.md');
        $this->assertFileExists($root.'/docs/DEPLOY.md');
    }

    public function test_disco_aulas_padrao_e_local(): void
    {
        $this->assertSame('local', config('filesystems.disks.aulas.driver'));
    }

    public function test_config_app_expoe_frontend_url(): void
    {
        $app = File::get(base_path('config/app.php'));
        $this->assertStringContainsString("'frontend_url'", $app);
    }

    public function test_teto_de_upload_e_35gb_com_partes(): void
    {
        $this->assertSame(35 * 1024 * 1024 * 1024, (int) config('biblioteca.upload_max_bytes'));
        $this->assertSame(100 * 1024 * 1024, (int) config('biblioteca.upload_part_bytes'));
        $conf = File::get(base_path('config/biblioteca.php'));
        $this->assertStringContainsString('upload_part_bytes', $conf);
    }

    public function test_artefatos_de_deploy_existem_na_raiz(): void
    {
        $root = dirname(base_path(), 2);

        $this->assertFileExists($root.'/.github/workflows/ci.yml');
        $this->assertFileExists($root.'/.github/workflows/deploy.yml');
        $this->assertFileExists($root.'/docs/DEPLOY.md');
        $this->assertFileExists($root.'/deploy/nginx/institutolg-player.conf');
        $this->assertFileExists($root.'/deploy/systemd/institutolg-player-queue.service');
        $this->assertFileExists($root.'/deploy/scripts/provision-ec2.sh');
    }

    public function test_deploy_yml_e_self_hosted_sem_ssh_inbound(): void
    {
        $yml = File::get(dirname(base_path(), 2).'/.github/workflows/deploy.yml');

        $this->assertStringContainsString('self-hosted', $yml);
        $this->assertStringContainsString('institutolgplayer_github', $yml);
        $this->assertStringContainsString('core.filemode false', $yml);
        $this->assertStringContainsString('institutolg-player-queue', $yml);
        $this->assertStringNotContainsString('SSH_HOST', $yml);
        $this->assertStringNotContainsString('SSH_USER', $yml);
        $this->assertStringNotContainsString('SSH_PRIVATE_KEY', $yml);
    }

    public function test_nginx_encaminha_player_publico_e_aceita_mp4_grande(): void
    {
        $conf = File::get(dirname(base_path(), 2).'/deploy/nginx/institutolg-player.conf');

        $this->assertStringContainsString('location ^~ /api', $conf);
        $this->assertStringContainsString('location ^~ /assistir', $conf);
        $this->assertStringContainsString('location ^~ /eduq', $conf);
        $this->assertStringContainsString('location ^~ /capa', $conf);
        $this->assertStringContainsString('client_max_body_size 35g', $conf);
        $this->assertStringContainsString('code/backend/public/index.php', $conf);
    }

    public function test_systemd_ouve_a_fila_biblioteca(): void
    {
        $unit = File::get(dirname(base_path(), 2).'/deploy/systemd/institutolg-player-queue.service');

        $this->assertStringContainsString('--queue=biblioteca', $unit);
        $this->assertStringContainsString('queue:work', $unit);
        $this->assertStringContainsString('--timeout=43200', $unit);
        $this->assertStringNotContainsString('queue:work database --sleep', $unit);
    }

    public function test_proxy_confiavel_esta_ligado_para_nginx(): void
    {
        $boot = File::get(base_path('bootstrap/app.php'));

        $this->assertStringContainsString('trustProxies', $boot);
    }

    public function test_phpunit_xml_so_aponta_pastas_que_existem(): void
    {
        $xml = simplexml_load_file(base_path('phpunit.xml'));
        $this->assertNotFalse($xml);

        foreach ($xml->testsuites->testsuite as $suite) {
            foreach ($suite->directory as $dir) {
                $path = base_path((string) $dir);
                $this->assertDirectoryExists($path, 'phpunit.xml aponta para pasta inexistente: '.$path);
            }
        }
    }
}
