<?php

namespace Tests\Feature;

use App\Models\Association;
use App\Models\CrewPlan;
use App\Models\Race;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_seeds_the_baie_des_mulets_association(): void
    {
        $this->seed(DatabaseSeeder::class);

        $association = Association::sole();
        $this->assertSame('Le Vauclin', $association->city);
        $this->assertStringContainsString('Baie des Mulets', $association->name);
        $this->assertTrue($association->boats()->where('name', 'Prixe – Midea')->exists());
        $this->assertTrue(CrewPlan::sole()->isValidated());
        $this->assertSame(8, Race::sole()->stages()->count());

        $this->actingAs(User::where('email', 'patron@yoleteam.test')->sole())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Baie des Mulets');
    }
}
