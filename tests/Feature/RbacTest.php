<?php

namespace Tests\Feature;

use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function sekolah(string $npsn): Sekolah
    {
        return Sekolah::create([
            'npsn' => $npsn,
            'nama' => "Sekolah $npsn",
            'jenjang' => 'SD',
            'status' => 'Negeri',
            'alamat' => 'Jl. Uji',
            'kecamatan' => 'Uji',
            'latitude' => -3.3,
            'longitude' => 114.59,
        ]);
    }

    private function user(string $role, ?Sekolah $sekolah = null): User
    {
        return User::factory()->create([
            'role' => $role,
            'sekolah_id' => $sekolah?->id,
        ]);
    }

    public function test_tamu_dialihkan_ke_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_login_gagal_dan_berhasil(): void
    {
        $u = $this->user('dinas');

        $this->post('/login', ['email' => $u->email, 'password' => 'salah'])
            ->assertSessionHasErrors('email');

        $this->post('/login', ['email' => $u->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($u);
    }

    public function test_admin_hanya_mengelola_pengguna(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->get('/admin/users')->assertOk();
        $this->actingAs($admin)->get('/dinas/sekolah')->assertForbidden();
    }

    public function test_admin_menambah_pengguna_sekolah(): void
    {
        $sekolah = $this->sekolah('11111111');

        $this->actingAs($this->user('admin'))->post('/admin/users', [
            'name' => 'Operator',
            'email' => 'op@uji.test',
            'role' => 'sekolah',
            'sekolah_id' => $sekolah->id,
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertRedirect('/admin/users');

        $this->assertDatabaseHas('users', [
            'email' => 'op@uji.test',
            'role' => 'sekolah',
            'sekolah_id' => $sekolah->id,
        ]);
    }

    public function test_role_sekolah_wajib_memilih_sekolah(): void
    {
        $this->actingAs($this->user('admin'))->post('/admin/users', [
            'name' => 'Operator',
            'email' => 'op@uji.test',
            'role' => 'sekolah',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertSessionHasErrors('sekolah_id');
    }

    public function test_dinas_mengelola_semua_sekolah(): void
    {
        $a = $this->sekolah('11111111');
        $dinas = $this->user('dinas');

        $this->actingAs($dinas)->get('/dinas/sekolah')->assertOk();
        $this->actingAs($dinas)->get("/sekolah/{$a->id}/edit")->assertOk();
        $this->actingAs($dinas)->get("/sekolah/{$a->id}/profil")->assertOk();
        $this->actingAs($dinas)->get('/admin/users')->assertForbidden();
    }

    public function test_sekolah_hanya_mengakses_sekolah_sendiri(): void
    {
        $a = $this->sekolah('11111111');
        $b = $this->sekolah('22222222');
        $op = $this->user('sekolah', $a);

        $this->actingAs($op)->get("/sekolah/{$a->id}/edit")->assertOk();
        $this->actingAs($op)->get("/sekolah/{$b->id}/edit")->assertForbidden();
        $this->actingAs($op)->get("/sekolah/{$b->id}/profil")->assertForbidden();
        $this->actingAs($op)->get('/dinas/sekolah')->assertForbidden();
        $this->actingAs($op)->get('/admin/users')->assertForbidden();
    }

    public function test_sekolah_tidak_dapat_mengubah_npsn(): void
    {
        $a = $this->sekolah('11111111');
        $op = $this->user('sekolah', $a);

        $this->actingAs($op)->put("/sekolah/{$a->id}", [
            'npsn' => '99999999',
            'nama' => 'Nama Baru',
            'jenjang' => 'SD',
            'status' => 'Negeri',
            'alamat' => 'Jl. Baru',
            'kecamatan' => 'Uji',
            'latitude' => -3.3001,
            'longitude' => 114.5901,
        ])->assertRedirect();

        $a->refresh();

        $this->assertSame('11111111', $a->npsn);
        $this->assertSame('Nama Baru', $a->nama);
    }

    public function test_sekolah_menyimpan_profil_sendiri(): void
    {
        $a = $this->sekolah('11111111');
        $op = $this->user('sekolah', $a);

        $this->actingAs($op)->put("/sekolah/{$a->id}/profil", [
            'kepala_sekolah' => 'Bapak Uji',
            'akreditasi' => 'A',
        ])->assertRedirect();

        $this->assertDatabaseHas('profil_sekolahs', [
            'sekolah_id' => $a->id,
            'kepala_sekolah' => 'Bapak Uji',
        ]);
    }

    public function test_sekolah_tidak_dapat_menghapus_sekolah(): void
    {
        $a = $this->sekolah('11111111');
        $op = $this->user('sekolah', $a);

        $this->actingAs($op)->delete("/dinas/sekolah/{$a->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('sekolahs', ['id' => $a->id]);
    }

        private function payloadSekolah(float $lat, float $lng): array
    {
        return [
            'npsn' => '11111111',
            'nama' => 'Sekolah Uji',
            'jenjang' => 'SD',
            'status' => 'Negeri',
            'alamat' => 'Jl. Uji',
            'kecamatan' => 'Uji',
            'latitude' => $lat,
            'longitude' => $lng,
        ];
    }

    public function test_sekolah_tidak_dapat_menggeser_koordinat_terlalu_jauh(): void
    {
        $a = $this->sekolah('11111111');
        $op = $this->user('sekolah', $a);

        $this->actingAs($op)
            ->put("/sekolah/{$a->id}", $this->payloadSekolah(-3.31, 114.6))
            ->assertSessionHasErrors('latitude');

        $a->refresh();
        $this->assertEquals(-3.3, (float) $a->latitude);
    }

    public function test_sekolah_boleh_menggeser_koordinat_dalam_batas(): void
    {
        $a = $this->sekolah('11111111');
        $op = $this->user('sekolah', $a);

        $this->actingAs($op)
            ->put("/sekolah/{$a->id}", $this->payloadSekolah(-3.3001, 114.5901))
            ->assertSessionHasNoErrors();

        $a->refresh();
        $this->assertEquals(-3.3001, (float) $a->latitude);
    }

    public function test_dinas_boleh_menggeser_koordinat_jauh(): void
    {
        $a = $this->sekolah('11111111');

        $this->actingAs($this->user('dinas'))
            ->put("/sekolah/{$a->id}", $this->payloadSekolah(-3.31, 114.6))
            ->assertSessionHasNoErrors();

        $a->refresh();
        $this->assertEquals(-3.31, (float) $a->latitude);
    }

    public function test_sekolah_dapat_mengunggah_logo(): void
    {
        Storage::fake('public');
        $a = $this->sekolah('11111111');
        $op = $this->user('sekolah', $a);

        $this->actingAs($op)->put("/sekolah/{$a->id}/profil", [
            'logo' => UploadedFile::fake()->image('logo.png', 100, 100),
        ])->assertSessionHasNoErrors();

        $path = $a->fresh()->profil->logo;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_logo_harus_berupa_gambar(): void
    {
        Storage::fake('public');
        $a = $this->sekolah('11111111');
        $op = $this->user('sekolah', $a);

        $this->actingAs($op)->put("/sekolah/{$a->id}/profil", [
            'logo' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('logo');
    }

    public function test_sekolah_tidak_dapat_mengunggah_logo_sekolah_lain(): void
    {
        Storage::fake('public');
        $a = $this->sekolah('11111111');
        $b = $this->sekolah('22222222');
        $op = $this->user('sekolah', $a);

        $this->actingAs($op)->put("/sekolah/{$b->id}/profil", [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertForbidden();
    }

    public function test_role_user_berupa_enum(): void
    {
        $u = $this->user('dinas');

        $this->assertSame(\App\Enums\Role::Dinas, $u->fresh()->role);
        $this->assertSame('Dinas Pendidikan', $u->roleLabel());
    }

    public function test_tamu_dapat_membuka_profil_publik(): void
    {
    $a = $this->sekolah('11111111');
    $a->profil()->create([
        'kepala_sekolah' => 'Bapak Uji',
        'email' => 'rahasia@uji.test',
    ]);

    $this->get("/profil/{$a->id}")
        ->assertOk()
        ->assertSee('Bapak Uji')
        ->assertDontSee('rahasia@uji.test');
    }

    public function test_profil_publik_sekolah_tanpa_profil_tetap_tampil(): void
    {
        $a = $this->sekolah('11111111');

        $this->get("/profil/{$a->id}")->assertOk();
    }

    public function test_profil_publik_sekolah_tidak_ada_menghasilkan_404(): void
    {
        $this->get('/profil/9999')->assertNotFound();
    }
}