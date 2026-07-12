<?php

namespace App\Enums;

enum PatientMatchStatus: string
{
    case Pending = 'pending';
    case Matched = 'matched';
    case MatchedWithDifferences = 'matched_with_differences';
    case NewPatient = 'new_patient';
    case IdentityConflict = 'identity_conflict';
    case InvalidBirthNumber = 'invalid_birth_number';
    case ManuallyLinked = 'manually_linked';
}
