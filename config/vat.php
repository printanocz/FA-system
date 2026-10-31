<?php

/**
 * Limity pro registraci k DPH (obrat za kalendářní rok). Při změně zákona stačí upravit tady.
 */
return [
    /** Při překročení se OSVČ stává plátcem od 1. 1. následujícího roku. */
    'registration_limit' => '2000000',

    /** Při překročení je plátcem už od následujícího dne. */
    'immediate_limit' => '2536500',

    /** Od tohoto podílu limitu (v %) se ukáže upozornění. */
    'warning_percent' => 80,
];
