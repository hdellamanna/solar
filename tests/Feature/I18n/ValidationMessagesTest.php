<?php

namespace Tests\Feature\I18n;

use Tests\TestCase;

/**
 * FASE 7 — i18n tri-língue. Verifies that Laravel's
 * `lang/{locale}/validation.php` files actually drive the
 * messages the user sees on a failed form submit.
 *
 * The 3 cases below cover the contract:
 *
 *   1. By default (pt-BR) a missing `motion_preference`
 *      yields a Portuguese error message — the
 *      `lang/pt-BR/validation.php` `required` row.
 *   2. With the active locale flipped to Spanish, the
 *      same failing validation yields the Spanish error
 *      message.
 *   3. With the active locale flipped to English, the
 *      error flips to English.
 *
 * We use the `validator()` helper directly with the
 * same rule the controller uses (`required`) and assert
 * the resolved message. This isolates the
 * locale-message resolution chain (Laravel
 * Validator → Translator → lang/{locale}/validation.php)
 * from the HTTP plumbing (which is well-covered by
 * other tests).
 *
 * (The `Settings/AppearanceController::update` route
 * is behind the `verified` + `two_factor` middleware
 * — POSTing a missing-field payload as a regular user
 * 302s to the verification notice, which is a
 * separate concern from the validation-message
 * resolution this test pins.)
 */
class ValidationMessagesTest extends TestCase
{
    // No RefreshDatabase: this test does not touch the
    // DB. We use the validator() helper directly to
    // assert the locale → message resolution chain.

    public function test_validation_messages_render_in_pt_br_by_default(): void
    {
        // The default locale is pt-BR. We invoke the
        // Validator directly with the same rule the
        // controller uses (`required`) and assert the
        // resolved message matches the pt-BR lang file.
        //
        // (We don't POST the form because the route is
        // behind the `verified` middleware — a
        // factory-created user is unverified by default
        // and the route 302s to the verification notice,
        // which is a separate concern from the
        // validation-message test.)
        $validator = validator(
            ['motion_preference' => null],
            ['motion_preference' => 'required'],
        );
        $this->assertTrue($validator->fails());
        $message = $validator->errors()->first('motion_preference');

        $expected = 'O campo motion preference é obrigatório.';
        $this->assertSame($expected, $message);

        // Sanity: the message must come from the
        // pt-BR lang file (it starts with "O campo",
        // which only the pt-BR file uses).
        $this->assertStringStartsWith('O campo', $message);
    }

    public function test_validation_messages_render_in_es_after_locale_change(): void
    {
        // Switch the active locale to Spanish. The
        // `trans_choice` / `trans` helpers the
        // Validator's MessageBag uses resolve against
        // the active locale — switching via
        // `app()->setLocale()` is enough to flip the
        // rendered message.
        $previous = app()->getLocale();
        app()->setLocale('es');

        try {
            $validator = validator(
                ['motion_preference' => null],
                ['motion_preference' => 'required'],
            );
            $this->assertTrue($validator->fails());
            $message = $validator->errors()->first('motion_preference');

            // Spanish: "El campo motion_preference es
            // obligatorio." (note: Spanish uses
            // "obligatorio" — distinct from the pt-BR
            // "obrigatório").
            $this->assertStringStartsWith('El campo', $message);
            $this->assertStringContainsString('obligatorio', $message);
        } finally {
            app()->setLocale($previous);
        }
    }

    public function test_validation_messages_render_in_en_after_locale_change(): void
    {
        $previous = app()->getLocale();
        app()->setLocale('en');

        try {
            $validator = validator(
                ['motion_preference' => null],
                ['motion_preference' => 'required'],
            );
            $this->assertTrue($validator->fails());
            $message = $validator->errors()->first('motion_preference');

            // English: "The motion_preference field is
            // required." — distinct prefix from pt-BR
            // ("O campo") and Spanish ("El campo").
            $this->assertStringStartsWith('The', $message);
            $this->assertStringContainsString('field is required', $message);
        } finally {
            app()->setLocale($previous);
        }
    }
}
