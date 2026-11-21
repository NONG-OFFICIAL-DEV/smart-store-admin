<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\AuthController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Personal app-shell preferences (layout/theme/accent/density + language) —
 * see me()'s 'preferred_language'/'ui_preferences' keys (MeEndpointShapeTest
 * covers the response shape elsewhere) for where the frontend reads these
 * back on login.
 */
class UpdatePreferencesTest extends TestCase
{
    use RefreshDatabase;

    private function callUpdatePreferences(array $payload)
    {
        $request = Request::create('/me/preferences', 'PATCH', $payload);

        return $this->app->make(AuthController::class)->updatePreferences($request);
    }

    public function test_saves_language_and_ui_preferences_together(): void
    {
        $user = User::create(['email' => 'u1@example.test', 'first_name' => 'U', 'last_name' => '1']);
        Auth::login($user);

        $this->callUpdatePreferences([
            'preferred_language' => 'km',
            'ui_preferences' => ['layout' => 'icon_rail', 'theme' => 'dark', 'accent' => 'blue', 'density' => 'compact'],
        ]);

        $user->refresh();
        $this->assertSame('km', $user->preferred_language);
        $this->assertSame(
            ['layout' => 'icon_rail', 'theme' => 'dark', 'accent' => 'blue', 'density' => 'compact'],
            $user->ui_preferences
        );
    }

    /**
     * A partial update (e.g. just switching accent from the live preview)
     * must not wipe out the rest of the previously-saved bag.
     */
    public function test_a_partial_update_merges_into_the_existing_bag_instead_of_replacing_it(): void
    {
        $user = User::create([
            'email' => 'u2@example.test', 'first_name' => 'U', 'last_name' => '2',
            'ui_preferences' => ['layout' => 'sidebar_only', 'theme' => 'light', 'accent' => 'teal', 'density' => 'comfortable'],
        ]);
        Auth::login($user);

        $this->callUpdatePreferences(['ui_preferences' => ['accent' => 'rose']]);

        $user->refresh();
        $this->assertSame(
            ['layout' => 'sidebar_only', 'theme' => 'light', 'accent' => 'rose', 'density' => 'comfortable'],
            $user->ui_preferences
        );
    }

    public function test_rejects_an_unknown_layout_value(): void
    {
        $user = User::create(['email' => 'u3@example.test', 'first_name' => 'U', 'last_name' => '3']);
        Auth::login($user);

        $this->expectException(ValidationException::class);
        $this->callUpdatePreferences(['ui_preferences' => ['layout' => 'not-a-real-layout']]);
    }

    /**
     * Sidebar collapse/expand state (Ctrl/Cmd+B or the floating handle) —
     * shared across Classic/Sidebar-only/Icon-rail, persisted the same way
     * as every other appearance preference.
     */
    public function test_saves_sidebar_collapsed_state(): void
    {
        $user = User::create(['email' => 'u4@example.test', 'first_name' => 'U', 'last_name' => '4']);
        Auth::login($user);

        $this->callUpdatePreferences(['ui_preferences' => ['sidebar_collapsed' => true]]);

        $user->refresh();
        $this->assertTrue($user->ui_preferences['sidebar_collapsed']);
    }

    public function test_rejects_a_non_boolean_sidebar_collapsed_value(): void
    {
        $user = User::create(['email' => 'u5@example.test', 'first_name' => 'U', 'last_name' => '5']);
        Auth::login($user);

        $this->expectException(ValidationException::class);
        $this->callUpdatePreferences(['ui_preferences' => ['sidebar_collapsed' => 'not-a-boolean']]);
    }
}
