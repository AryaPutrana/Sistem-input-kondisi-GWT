<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Disk privat 'evidence' selalu di-fake di sini, bukan di setiap test.
        // Tanpa ini, test yang melakukan upload foto akan menulis file sungguhan
        // ke storage/app/evidence karena tidak ada test yang memanggil
        // Storage::fake('evidence') secara eksplisit.
        Storage::fake('evidence');
    }
}
