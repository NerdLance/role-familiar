<?php

namespace App\Enums;

enum CompetencyCategory: string
{
    case Technical = 'technical';
    case Behavioral = 'behavioral';
    case Leadership = 'leadership';
    case Domain = 'domain';
    case Tooling = 'tooling';
    case Communication = 'communication';
    case Delivery = 'delivery';
}
