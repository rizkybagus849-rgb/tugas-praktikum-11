<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookApiTest extends TestCase
{
    use RefreshDatabase;

    private function login(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_tanpa_login_ditolak(): void
    {
        $this->getJson('/api/books')->assertUnauthorized();
    }

    public function test_list_dan_pencarian(): void
    {
        $this->login();
        Book::factory()->create(['title' => 'Laskar Pelangi', 'author' => 'Andrea Hirata']);
        Book::factory()->count(4)->create(['title' => 'Buku Acak', 'author' => 'Penulis Lain']);

        $this->getJson('/api/books?per_page=3')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data', 'links', 'meta']);

        $this->getJson('/api/books?search=Laskar')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Laskar Pelangi');
    }

    public function test_tambah_buku_dan_validasi(): void
    {
        $this->login();

        $payload = [
            'title' => 'Bumi Manusia',
            'author' => 'Pramoedya Ananta Toer',
            'isbn' => '9789799731234',
            'published_year' => 1980,
            'stock' => 5,
        ];

        $this->postJson('/api/books', $payload)
            ->assertCreated()
            ->assertJsonPath('data.title', 'Bumi Manusia');

        $this->assertDatabaseHas('books', ['isbn' => '9789799731234']);

        // ISBN kembar -> 422
        $this->postJson('/api/books', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('isbn');

        // field wajib kosong -> 422
        $this->postJson('/api/books', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'author', 'isbn', 'published_year', 'stock']);
    }

    public function test_detail_dan_ubah_buku(): void
    {
        $this->login();
        $book = Book::factory()->create(['stock' => 1]);

        $this->getJson("/api/books/{$book->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $book->id);

        $this->patchJson("/api/books/{$book->id}", ['stock' => 99])
            ->assertOk()
            ->assertJsonPath('data.stock', 99);

        $this->putJson("/api/books/{$book->id}", ['title' => 'Judul Baru', 'isbn' => $book->isbn])
            ->assertOk()
            ->assertJsonPath('data.title', 'Judul Baru');

        $this->getJson('/api/books/999999')->assertNotFound();
    }

    public function test_hapus_buku(): void
    {
        $this->login();
        $book = Book::factory()->create();

        $this->deleteJson("/api/books/{$book->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Buku berhasil dihapus');

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }
}
