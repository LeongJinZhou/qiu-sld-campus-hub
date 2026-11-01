<?php

use App\Models\Student;

it('finds active students by name, email or student ID for committee forms only', function () {
    $f = proposalFixture();
    $student = Student::where('student_number', 'STD20240010')->first();
    $plain = otherStudent();

    $this->actingAs($f['president'])->getJson('/people/lookup?q='.urlencode(explode(' ', $student->name)[0]))
        ->assertOk()->assertJsonFragment(['student_number' => 'STD20240010', 'email' => $student->email]);
    $this->actingAs($f['president'])->getJson('/people/lookup?q=STD20240010')->assertJsonPath('0.name', $student->name);
    $this->actingAs($plain)->getJson('/people/lookup?q=STD2024')->assertForbidden();
});
