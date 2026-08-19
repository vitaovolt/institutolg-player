<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsuarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_usuarios_autenticado(): void
    {
        $coordenacao = $this->comoCoordenacao();
        User::factory()->create(['name' => 'Ana', 'email' => 'ana@institutolg.local']);

        $emails = collect($this->getJson('/api/v1/usuarios')->assertOk()->json('data'))->pluck('email');

        $this->assertTrue($emails->contains('ana@institutolg.local'));
        $this->assertTrue($emails->contains($coordenacao->email));
        $this->assertDatabaseHas('users', ['email' => 'ana@institutolg.local']);
    }

    public function test_cria_usuario_e_ele_entra_no_banco(): void
    {
        $this->comoCoordenacao();

        $this->postJson('/api/v1/usuarios', [
            'name' => 'Bruno',
            'email' => 'Bruno@InstitutoLG.local',
            'password' => 'senha-segura',
            'ativo' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Bruno')
            ->assertJsonPath('data.email', 'bruno@institutolg.local')
            ->assertJsonPath('data.ativo', true)
            ->assertJsonMissingPath('data.password');

        $this->assertDatabaseHas('users', [
            'name' => 'Bruno',
            'email' => 'bruno@institutolg.local',
            'ativo' => true,
        ]);
    }

    public function test_atualiza_nome_e_desativa_outro_usuario_revogando_token(): void
    {
        $this->comoCoordenacao();
        $alvo = User::factory()->create([
            'name' => 'Carla',
            'email' => 'carla@institutolg.local',
            'password' => 'password',
        ]);
        $token = $alvo->createToken('spa')->plainTextToken;

        $this->putJson('/api/v1/usuarios/'.$alvo->id, [
            'name' => 'Carla Souza',
            'email' => 'carla@institutolg.local',
            'ativo' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Carla Souza')
            ->assertJsonPath('data.ativo', false);

        $this->assertDatabaseHas('users', [
            'id' => $alvo->id,
            'name' => 'Carla Souza',
            'ativo' => false,
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $alvo->id,
            'tokenable_type' => User::class,
        ]);

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_nao_exclui_a_propria_conta(): void
    {
        $eu = $this->comoCoordenacao();

        $this->deleteJson('/api/v1/usuarios/'.$eu->id)
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('users', ['id' => $eu->id]);
    }

    public function test_nao_desativa_a_propria_conta(): void
    {
        $eu = $this->comoCoordenacao();

        $this->putJson('/api/v1/usuarios/'.$eu->id, [
            'name' => $eu->name,
            'email' => $eu->email,
            'ativo' => false,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ativo']);

        $this->assertTrue($eu->fresh()->ativo);
    }

    public function test_exclui_outro_usuario_e_apaga_tokens(): void
    {
        $this->comoCoordenacao();
        $alvo = User::factory()->create(['email' => 'sair@institutolg.local']);
        $alvo->createToken('spa');

        $this->deleteJson('/api/v1/usuarios/'.$alvo->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('users', ['id' => $alvo->id]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $alvo->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_respeita_teto_de_cinco_contas(): void
    {
        $this->comoCoordenacao();
        User::factory()->count(4)->create();

        $this->postJson('/api/v1/usuarios', [
            'name' => 'Excedente',
            'email' => 'sexto@institutolg.local',
            'password' => 'senha-segura',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseMissing('users', ['email' => 'sexto@institutolg.local']);
        $this->assertDatabaseCount('users', 5);
    }

    public function test_anonimo_nao_lista_nem_cria(): void
    {
        $this->getJson('/api/v1/usuarios')->assertUnauthorized();
        $this->postJson('/api/v1/usuarios', [
            'name' => 'Invasor',
            'email' => 'invasor@institutolg.local',
            'password' => 'senha-segura',
        ])->assertUnauthorized();

        $this->assertDatabaseMissing('users', ['email' => 'invasor@institutolg.local']);
    }

    public function test_conta_inativa_nao_cria_usuario(): void
    {
        $this->comoInativo();

        $this->postJson('/api/v1/usuarios', [
            'name' => 'Invasor',
            'email' => 'invasor@institutolg.local',
            'password' => 'senha-segura',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'invasor@institutolg.local']);
    }

    public function test_recurso_inexistente_retorna_404(): void
    {
        $this->comoCoordenacao();

        $this->getJson('/api/v1/usuarios/999999')->assertNotFound();
        $this->putJson('/api/v1/usuarios/999999', [
            'name' => 'X',
            'email' => 'x@institutolg.local',
        ])->assertNotFound();
        $this->deleteJson('/api/v1/usuarios/999999')->assertNotFound();
    }
}
