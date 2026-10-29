<?php

/*
 * Builds resources/templates/filled/* from resources/templates/original/* by writing ${field} markers into
 * the blanks. Originals are never modified. Field sources are documented in resources/templates/maps/*.json.
 *
 * Usage: php tools/templates/make_placeholders.php
 */

require __DIR__.'/DocxPlaceholders.php';

$root = dirname(__DIR__, 2);
$original = "{$root}/resources/templates/original";
$filled = "{$root}/resources/templates/filled";

// ---- 06 Event Proposal ------------------------------------------------------------------------------------
$name = '06. Event Proposal Template 052026.docx';
$d = new DocxPlaceholders("{$original}/{$name}");

$t = $d->block(3); // Concept paper table
$d->setText($d->cellParagraph($t, 0, 1), '${to}');
$d->setText($d->cellParagraph($t, 1, 1), '${from}');
$d->setText($d->cellParagraph($t, 2, 1), '${submission_date}');
$d->setText($d->cellParagraph($t, 3, 1), '${event_date}');
$d->setText($d->cellParagraph($t, 3, 3), '${event_time}');
$d->setText($d->cellParagraph($t, 4, 1), '${name}');
$d->setText($d->cellParagraph($t, 5, 1), '${summary}');
$natures = [7 => ['sports', 'competition', 'humanity'], 8 => ['social', 'fundraising', 'environmental'], 9 => ['seminar', 'exhibition', 'others']];
foreach ($natures as $row => $keys) {
    foreach ($keys as $i => $key) {
        $d->setText($d->cellParagraph($t, $row, 2 + $i * 2), "\${nature_{$key}}");
    }
}
$specify = $d->cellParagraph($t, 10, 1);
$d->setText($specify, 'Please specify: ${nature_other}');
$d->setText($d->cellParagraph($t, 11, 2, 0), ': ${cca_participant} CCA Hours');
$d->setText($d->cellParagraph($t, 11, 2, 1), ': ${cca_committee} CCA Hours');
$d->setText($d->cellParagraph($t, 12, 1), '${duration}');
$d->setText($d->cellParagraph($t, 13, 1), '${venue}');
$d->setText($d->cellParagraph($t, 14, 2), '${mode_physical}');
$d->setText($d->cellParagraph($t, 14, 4), '${mode_virtual}');
foreach (range(15, 24) as $i => $row) {
    $d->setText($d->cellParagraph($t, $row, 2), '${sdg_'.($i + 1).'}');
    if ($i < 7) {
        $d->setText($d->cellParagraph($t, $row, 4), '${sdg_'.($i + 11).'}');
    }
}
$d->setText($d->cellParagraph($t, 25, 1), '${collaboration}');
$d->setText($d->cellParagraph($t, 26, 1), '${total_budget}');
foreach (['own', 'club', 'qiu'] as $i => $key) {
    $d->setText($d->cellParagraph($t, 27, 2 + $i * 2), "\${fund_{$key}}");
}

$d->setText($d->paragraphContaining('XXX PROPOSAL'), '${name_upper} PROPOSAL');
$d->setText($d->paragraphContaining('dd/mm/yyyy'), '${tentative_date}');
$d->setText($d->paragraphContaining('The Curve, Level 3'), '${venue}');
$d->setText($d->paragraphContaining('X Hours'), '${duration}');
$d->setText($d->paragraphContaining('8.00 AM – 12.00 PM'), '${time_range}');
$d->setText($d->paragraphContaining('XXX Club / Society'), '${from}');
$body = $d->paragraphContaining('Brief introduction of the event'); // body-text style for inserted paragraphs
$d->setText($body, '${introduction}');

// Objectives: one numbered paragraph inside a clone block, so each objective keeps the numbering.
$objective = $d->block(31);
$d->setText($objective, '${objective}');
$objective->parentNode->insertBefore($d->block(31)->cloneNode(false), $objective);
$d->setText($d->block(31), '${objectives}');
$d->insertAfter($objective, '${/objectives}');
$d->block(34)->parentNode->removeChild($d->block(34)); // second sample numbered line

$d->setText($d->paragraphContaining('Expected number of participants:'), 'Expected number of participants: ${expected_participants}');
$d->setText($d->paragraphContaining('Eligibility:'), 'Eligibility: ${eligibility}');
$d->insertAfter($d->paragraphContaining('ICT & Maintenance Requirement'), '${ict_requirements}', $body);
$d->setText($d->paragraphContaining('Transportation required / not required'), '${transport}');
$d->setText($d->paragraphContaining('Departure Time:'), 'Departure Time: ${departure_time}');
$d->setText($d->paragraphContaining('Pickup Point:', 0), 'Pickup Point: ${departure_pickup}');
$d->setText($d->paragraphContaining('Estimated Return Time:'), 'Estimated Return Time: ${return_time}');
$d->setText($d->paragraphContaining('Pickup Point:', 1), 'Pickup Point: ${return_pickup}');

$table = fn (string $header) => $d->paragraphContaining($header)->parentNode->parentNode->parentNode;
$activities = $table('Name of the Activity');
$d->setText($d->cellParagraph($activities, 1, 0), '${activity_name}');
$d->setText($d->cellParagraph($activities, 1, 1), '${activity_description}');
$d->removeRows($activities, 2, 8);

$itinerary = $table('TIME START');
foreach (['itinerary_start', 'itinerary_end', 'itinerary_agenda'] as $c => $field) {
    $d->setText($d->cellParagraph($itinerary, 1, $c), "\${{$field}}");
}
$d->removeRows($itinerary, 2, 8);

$committee = $table('Responsibility');
foreach (['committee_name', 'committee_role', 'committee_responsibility'] as $c => $field) {
    $d->setText($d->cellParagraph($committee, 1, $c), "\${{$field}}");
}
$d->removeRows($committee, 2, 4);

$budget = $table('Description of Items');
foreach (['revenue' => 2, 'expense' => 11] as $prefix => $row) {
    foreach (['no', 'description', 'price', 'quantity', 'total'] as $c => $field) {
        $d->setText($d->cellParagraph($budget, $row, $c), "\${{$prefix}_{$field}}");
    }
}
$d->setText($d->cellParagraph($budget, 8, 4), '${revenue_sum}');
$d->setText($d->cellParagraph($budget, 15, 4), '${expense_sum}');
$d->setText($d->cellParagraph($budget, 17, 2), '${profit_loss}');
$d->removeRows($budget, 12, 13);
$d->removeRows($budget, 3, 6);

$conclusion = $d->paragraphContaining('Conclusion');
$d->insertAfter($conclusion, '${conclusion}', $body);

// Approval & Verification: six signers in chain order. Names come from records, never the printed ones.
$labels = ['Prepared by:', 'Reviewed by:', 'Reviewed by:', 'Recommended by:', 'Verified by:', 'Approved by:'];
$seen = [];
foreach ($labels as $n => $label) {
    $k = $n + 1;
    $seen[$label] = ($seen[$label] ?? -1) + 1;
    $heading = $d->paragraphContaining($label, $seen[$label]);
    $line = $heading;
    while (! str_contains($d->text($line), '_____')) {
        $line = $line->nextSibling;
    }
    $d->setText($line->previousSibling instanceof DOMElement ? $line->previousSibling : $line, "\${sig{$k}_image}");
    $nameLine = $line->nextSibling;
    $d->setText($nameLine, "Name: \${sig{$k}_name}");
    $designation = $nameLine->nextSibling;
    $d->setText($designation, "Designation: \${sig{$k}_designation}");
    if ($k < 6) {
        $d->setText($designation->nextSibling, "Department: \${sig{$k}_department}");
        $d->setText($designation->nextSibling->nextSibling, "Date: \${sig{$k}_date}");
    } else {
        $d->setText($designation->nextSibling, "Date: \${sig{$k}_date}");
    }
}
$d->setText($d->paragraphContaining('Committee:'), 'Committee: ${cca_approved_committee}');
$d->setText($d->paragraphContaining('Student Helper:'), 'Student Helper: ${cca_approved_helper}');
$d->setText($d->paragraphContaining('Participants:'), 'Participants: ${cca_approved_participant}');

$d->save("{$filled}/{$name}");
echo "Wrote {$filled}/{$name}\n";
