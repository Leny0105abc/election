<?php

use App\Enums\ElectionStatus;

it('follows the election lifecycle', function (ElectionStatus $from, array $to) {
    expect($from->nextStatuses())->toBe($to);
})->with([
    'draft' => [ElectionStatus::Draft, [ElectionStatus::Scheduled]],
    'scheduled' => [ElectionStatus::Scheduled, [ElectionStatus::Draft, ElectionStatus::Open]],
    'open' => [ElectionStatus::Open, [ElectionStatus::Closed]],
    'closed' => [ElectionStatus::Closed, [ElectionStatus::Finalized]],
    'finalized' => [ElectionStatus::Finalized, [ElectionStatus::Archived]],
    'archived' => [ElectionStatus::Archived, []],
]);

it('allows ballot changes only before voting opens', function () {
    $editable = array_filter(ElectionStatus::cases(), fn ($s) => $s->isEditable());

    expect(array_values($editable))->toBe([ElectionStatus::Draft, ElectionStatus::Scheduled]);
});

it('keeps vote totals sealed until voting closes', function () {
    $canvass = array_filter(ElectionStatus::cases(), fn ($s) => $s->allowsCanvass());

    expect(array_values($canvass))->toBe([ElectionStatus::Closed, ElectionStatus::Finalized, ElectionStatus::Archived]);
});
