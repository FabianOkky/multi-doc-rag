<?php

use App\Services\AnswerLanguage;

test('an unknown or missing answer language falls back to auto', function (?string $value) {
    expect(AnswerLanguage::fromValue($value))->toBe(AnswerLanguage::Auto);
})->with([
    'null' => null,
    'empty' => '',
    'unsupported' => 'fr',
    'nonsense' => 'not-a-language',
]);

test('every answer language is offered in the picker, keyed by its value', function () {
    expect(AnswerLanguage::options())->toBe([
        'auto' => AnswerLanguage::Auto,
        'id' => AnswerLanguage::Indonesian,
        'en' => AnswerLanguage::English,
    ]);
});

test('every answer language repeats itself on the user turn', function (AnswerLanguage $language) {
    // The reminder is what actually decides the reply language: replayed history
    // out-weighs the system prompt, so even "auto" has to restate itself here, or
    // an English question asked after a few Indonesian turns comes back wrong.
    expect($language->turnReminder())->not->toBe('');
})->with(AnswerLanguage::cases());
