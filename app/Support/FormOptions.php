<?php

namespace App\Support;

final class FormOptions
{
    public const BLOOD = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
    public const RELIGION = ['Islam', 'Hindu', 'Christian', 'Buddhist', 'Other'];
    public const GENDER = ['Female', 'Male', 'Other'];
    public const OCCUPATION = ['Service', 'Business', 'House Wife', 'Farmer', 'Teacher', 'Doctor', 'Engineer', 'Other'];
    public const GUARDIAN_TYPE = ['Father', 'Mother', 'Uncle', 'Aunt', 'Grandparent', 'Other'];
    public const PHONE_REGEX = '/^01[3-9]\d{8}$/';
}
