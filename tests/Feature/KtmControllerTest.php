<?php

namespace Tests\Feature;

use App\Models\Ktm;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class KtmControllerTest extends TestCase
{
    public function test_rejected_application_can_be_submitted_again(): void
    {
        Storage::fake('public');
        $rejectedKtm = $this->createKtm('rejected');

        $response = $this->post(route('ktm.store'), $this->validSubmission());

        $newKtm = Ktm::where('nim', $rejectedKtm->nim)
            ->where('status', 'pending')
            ->firstOrFail();

        $response->assertRedirect(route('ktm.success', $newKtm->kode_pengajuan));
        $this->assertDatabaseCount('ktms', 2);
        $this->assertDatabaseHas('ktms', [
            'id' => $rejectedKtm->id,
            'status' => 'rejected',
        ]);
        $this->assertDatabaseHas('ktms', [
            'id' => $newKtm->id,
            'nim' => $rejectedKtm->nim,
            'status' => 'pending',
        ]);
    }

    #[DataProvider('nonRejectedStatuses')]
    public function test_duplicate_application_is_rejected_when_existing_status_is_not_rejected(string $status): void
    {
        Storage::fake('public');
        $existingKtm = $this->createKtm($status);

        $response = $this->from(route('ktm.create'))
            ->post(route('ktm.store'), $this->validSubmission());

        $response->assertRedirect(route('ktm.create'))
            ->assertSessionHasErrors(['nim' => 'NIM sudah terdaftar.']);
        $this->assertDatabaseCount('ktms', 1);
        $this->assertDatabaseHas('ktms', [
            'id' => $existingKtm->id,
            'status' => $status,
        ]);
    }

    public static function nonRejectedStatuses(): array
    {
        return [
            'pending' => ['pending'],
            'approved' => ['approved'],
        ];
    }

    public function test_backend_validation_messages_mark_invalid_fields_red_and_valid_fields_green(): void
    {
        $response = $this->from(route('ktm.create'))
            ->followingRedirects()
            ->post(route('ktm.store'), [
                'nama' => 'Nama Valid',
            ]);

        $response->assertOk()
            ->assertSeeText('Nama Valid')
            ->assertSeeText('NIM harus diisi.')
            ->assertSeeText('Pas foto harus diunggah.')
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('border-red-500', false)
            ->assertSee('border-green-500', false);
    }

    private function createKtm(string $status): Ktm
    {
        return Ktm::create([
            'nama' => 'Nama Mahasiswa',
            'nim' => '123456789',
            'email' => 'mahasiswa@example.com',
            'program_studi' => 'Sistem Informasi',
            'angkatan' => 2023,
            'foto_path' => 'foto-ktm/existing.png',
            'kode_pengajuan' => 'KTM-EXISTING',
            'token_verifikasi' => '123e4567-e89b-12d3-a456-426614174000',
            'status' => $status,
        ]);
    }

    private function validSubmission(): array
    {
        return [
            'nama' => 'Nama Mahasiswa',
            'nim' => '123456789',
            'email' => 'mahasiswa@example.com',
            'program_studi' => 'Sistem Informasi',
            'angkatan' => 2023,
            'foto' => UploadedFile::fake()->image('mahasiswa.png'),
            'persetujuan' => '1',
        ];
    }
}
