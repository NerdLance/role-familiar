<?php

namespace App\Enums;

enum Seniority: string
{
    case Intern = 'intern';
    case Junior = 'junior';
    case Mid = 'mid';
    case Senior = 'senior';
    case Staff = 'staff';
    case Principal = 'principal';
    case Lead = 'lead';
    case Manager = 'manager';
    case Director = 'director';
    case Executive = 'executive';
    case Unspecified = 'unspecified';
}
