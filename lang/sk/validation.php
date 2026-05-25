<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Nasledujúce jazykové riadky obsahujú predvolené chybové hlásenia
    | používané validačnou triedou. Niektoré pravidlá majú viacero verzií,
    | napríklad pravidlá pre veľkosť. Tieto texty môžete upraviť podľa potreby.
    |
    */

    'accepted' => 'Pole :attribute musí byť prijaté.',
    'accepted_if' => 'Pole :attribute musí byť prijaté, keď :other je :value.',
    'active_url' => 'Pole :attribute musí byť platná URL adresa.',
    'after' => 'Pole :attribute musí byť dátum po :date.',
    'after_or_equal' => 'Pole :attribute musí byť dátum po alebo rovný :date.',
    'alpha' => 'Pole :attribute môže obsahovať iba písmená.',
    'alpha_dash' => 'Pole :attribute môže obsahovať iba písmená, čísla, pomlčky a podčiarkovníky.',
    'alpha_num' => 'Pole :attribute môže obsahovať iba písmená a čísla.',
    'any_of' => 'Pole :attribute je neplatné.',
    'array' => 'Pole :attribute musí byť pole.',
    'ascii' => 'Pole :attribute môže obsahovať iba jednobajtové alfanumerické znaky a symboly.',
    'before' => 'Pole :attribute musí byť dátum pred :date.',
    'before_or_equal' => 'Pole :attribute musí byť dátum pred alebo rovný :date.',

    'between' => [
        'array' => 'Pole :attribute musí obsahovať od :min do :max položiek.',
        'file' => 'Pole :attribute musí mať od :min do :max kilobajtov.',
        'numeric' => 'Pole :attribute musí byť od :min do :max.',
        'string' => 'Pole :attribute musí mať od :min do :max znakov.',
    ],

    'boolean' => 'Pole :attribute musí byť pravda alebo nepravda.',
    'can' => 'Pole :attribute obsahuje nepovolenú hodnotu.',
    'confirmed' => 'Pole :attribute sa nezhoduje s potvrdením.',
    'contains' => 'V poli :attribute chýba požadovaná hodnota.',
    'current_password' => 'Heslo je nesprávne.',
    'date' => 'Pole :attribute musí byť platný dátum.',
    'date_equals' => 'Pole :attribute musí byť dátum rovný :date.',
    'date_format' => 'Pole :attribute musí zodpovedať formátu :format.',
    'decimal' => 'Pole :attribute musí mať :decimal desatinných miest.',
    'declined' => 'Pole :attribute musí byť odmietnuté.',
    'declined_if' => 'Pole :attribute musí byť odmietnuté, keď :other je :value.',
    'different' => 'Pole :attribute a :other musia byť odlišné.',
    'digits' => 'Pole :attribute musí mať :digits číslic.',
    'digits_between' => 'Pole :attribute musí mať od :min do :max číslic.',
    'dimensions' => 'Pole :attribute má neplatné rozmery obrázka.',
    'distinct' => 'Pole :attribute obsahuje duplicitnú hodnotu.',
    'doesnt_contain' => 'Pole :attribute nesmie obsahovať žiadnu z nasledujúcich hodnôt: :values.',
    'doesnt_end_with' => 'Pole :attribute nesmie končiť jednou z nasledujúcich hodnôt: :values.',
    'doesnt_start_with' => 'Pole :attribute nesmie začínať jednou z nasledujúcich hodnôt: :values.',
    'email' => 'Pole :attribute musí byť platná e-mailová adresa.',
    'encoding' => 'Pole :attribute musí byť kódované v :encoding.',
    'ends_with' => 'Pole :attribute musí končiť jednou z nasledujúcich hodnôt: :values.',
    'enum' => 'Vybraná hodnota poľa :attribute je neplatná.',
    'exists' => 'Vybraná hodnota poľa :attribute je neplatná.',
    'extensions' => 'Pole :attribute musí mať jednu z nasledujúcich prípon: :values.',
    'file' => 'Pole :attribute musí byť súbor.',
    'filled' => 'Pole :attribute musí obsahovať hodnotu.',

    'gt' => [
        'array' => 'Pole :attribute musí obsahovať viac ako :value položiek.',
        'file' => 'Pole :attribute musí byť väčšie ako :value kilobajtov.',
        'numeric' => 'Pole :attribute musí byť väčšie ako :value.',
        'string' => 'Pole :attribute musí mať viac ako :value znakov.',
    ],

    'gte' => [
        'array' => 'Pole :attribute musí obsahovať :value alebo viac položiek.',
        'file' => 'Pole :attribute musí byť väčšie alebo rovné :value kilobajtom.',
        'numeric' => 'Pole :attribute musí byť väčšie alebo rovné :value.',
        'string' => 'Pole :attribute musí mať :value alebo viac znakov.',
    ],

    'hex_color' => 'Pole :attribute musí byť platná hexadecimálna farba.',
    'image' => 'Pole :attribute musí byť obrázok.',
    'in' => 'Vybraná hodnota poľa :attribute je neplatná.',
    'in_array' => 'Pole :attribute musí existovať v :other.',
    'in_array_keys' => 'Pole :attribute musí obsahovať aspoň jeden z nasledujúcich kľúčov: :values.',
    'integer' => 'Pole :attribute musí byť celé číslo.',
    'ip' => 'Pole :attribute musí byť platná IP adresa.',
    'ipv4' => 'Pole :attribute musí byť platná IPv4 adresa.',
    'ipv6' => 'Pole :attribute musí byť platná IPv6 adresa.',
    'json' => 'Pole :attribute musí byť platný JSON reťazec.',
    'list' => 'Pole :attribute musí byť zoznam.',
    'lowercase' => 'Pole :attribute musí byť malými písmenami.',

    'lt' => [
        'array' => 'Pole :attribute musí obsahovať menej ako :value položiek.',
        'file' => 'Pole :attribute musí byť menšie ako :value kilobajtov.',
        'numeric' => 'Pole :attribute musí byť menšie ako :value.',
        'string' => 'Pole :attribute musí mať menej ako :value znakov.',
    ],

    'lte' => [
        'array' => 'Pole :attribute nesmie obsahovať viac ako :value položiek.',
        'file' => 'Pole :attribute musí byť menšie alebo rovné :value kilobajtom.',
        'numeric' => 'Pole :attribute musí byť menšie alebo rovné :value.',
        'string' => 'Pole :attribute musí mať najviac :value znakov.',
    ],

    'mac_address' => 'Pole :attribute musí byť platná MAC adresa.',

    'max' => [
        'array' => 'Pole :attribute nesmie obsahovať viac ako :max položiek.',
        'file' => 'Pole :attribute nesmie byť väčšie ako :max kilobajtov.',
        'numeric' => 'Pole :attribute nesmie byť väčšie ako :max.',
        'string' => 'Pole :attribute nesmie mať viac ako :max znakov.',
    ],

    'max_digits' => 'Pole :attribute nesmie mať viac ako :max číslic.',
    'mimes' => 'Pole :attribute musí byť súbor typu: :values.',
    'mimetypes' => 'Pole :attribute musí byť súbor typu: :values.',

    'min' => [
        'array' => 'Pole :attribute musí obsahovať aspoň :min položiek.',
        'file' => 'Pole :attribute musí mať aspoň :min kilobajtov.',
        'numeric' => 'Pole :attribute musí byť aspoň :min.',
        'string' => 'Pole :attribute musí mať aspoň :min znakov.',
    ],

    'min_digits' => 'Pole :attribute musí mať aspoň :min číslic.',
    'missing' => 'Pole :attribute musí chýbať.',
    'missing_if' => 'Pole :attribute musí chýbať, keď :other je :value.',
    'missing_unless' => 'Pole :attribute musí chýbať, pokiaľ :other nie je :value.',
    'missing_with' => 'Pole :attribute musí chýbať, keď je prítomné :values.',
    'missing_with_all' => 'Pole :attribute musí chýbať, keď sú prítomné :values.',
    'multiple_of' => 'Pole :attribute musí byť násobkom :value.',
    'not_in' => 'Vybraná hodnota poľa :attribute je neplatná.',
    'not_regex' => 'Formát poľa :attribute je neplatný.',
    'numeric' => 'Pole :attribute musí byť číslo.',

    'password' => [
        'letters' => 'Pole :attribute musí obsahovať aspoň jedno písmeno.',
        'mixed' => 'Pole :attribute musí obsahovať aspoň jedno veľké a jedno malé písmeno.',
        'numbers' => 'Pole :attribute musí obsahovať aspoň jedno číslo.',
        'symbols' => 'Pole :attribute musí obsahovať aspoň jeden symbol.',
        'uncompromised' => 'Zadané :attribute sa objavilo v úniku dát. Zvoľte iné :attribute.',
    ],

    'present' => 'Pole :attribute musí byť prítomné.',
    'present_if' => 'Pole :attribute musí byť prítomné, keď :other je :value.',
    'present_unless' => 'Pole :attribute musí byť prítomné, pokiaľ :other nie je :value.',
    'present_with' => 'Pole :attribute musí byť prítomné, keď je prítomné :values.',
    'present_with_all' => 'Pole :attribute musí byť prítomné, keď sú prítomné :values.',
    'prohibited' => 'Pole :attribute je zakázané.',
    'prohibited_if' => 'Pole :attribute je zakázané, keď :other je :value.',
    'prohibited_if_accepted' => 'Pole :attribute je zakázané, keď je :other prijaté.',
    'prohibited_if_declined' => 'Pole :attribute je zakázané, keď je :other odmietnuté.',
    'prohibited_unless' => 'Pole :attribute je zakázané, pokiaľ :other nie je v :values.',
    'prohibits' => 'Pole :attribute zakazuje, aby bolo prítomné pole :other.',
    'regex' => 'Formát poľa :attribute je neplatný.',
    'required' => 'Pole :attribute je povinné.',
    'required_array_keys' => 'Pole :attribute musí obsahovať položky pre: :values.',
    'required_if' => 'Pole :attribute je povinné, keď :other je :value.',
    'required_if_accepted' => 'Pole :attribute je povinné, keď je :other prijaté.',
    'required_if_declined' => 'Pole :attribute je povinné, keď je :other odmietnuté.',
    'required_unless' => 'Pole :attribute je povinné, pokiaľ :other nie je v :values.',
    'required_with' => 'Pole :attribute je povinné, keď je prítomné :values.',
    'required_with_all' => 'Pole :attribute je povinné, keď sú prítomné :values.',
    'required_without' => 'Pole :attribute je povinné, keď nie je prítomné :values.',
    'required_without_all' => 'Pole :attribute je povinné, keď nie je prítomná žiadna z hodnôt :values.',
    'same' => 'Pole :attribute sa musí zhodovať s poľom :other.',

    'size' => [
        'array' => 'Pole :attribute musí obsahovať :size položiek.',
        'file' => 'Pole :attribute musí mať :size kilobajtov.',
        'numeric' => 'Pole :attribute musí byť :size.',
        'string' => 'Pole :attribute musí mať :size znakov.',
    ],

    'starts_with' => 'Pole :attribute musí začínať jednou z nasledujúcich hodnôt: :values.',
    'string' => 'Pole :attribute musí byť reťazec.',
    'timezone' => 'Pole :attribute musí byť platné časové pásmo.',
    'unique' => 'Hodnota poľa :attribute sa už používa.',
    'uploaded' => 'Pole :attribute sa nepodarilo nahrať.',
    'uppercase' => 'Pole :attribute musí byť veľkými písmenami.',
    'url' => 'Pole :attribute musí byť platná URL adresa.',
    'ulid' => 'Pole :attribute musí byť platný ULID.',
    'uuid' => 'Pole :attribute musí byť platný UUID.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Tu môžete špecifikovať vlastné validačné správy pre atribúty pomocou
    | konvencie "attribute.rule". Vďaka tomu môžete rýchlo nastaviť konkrétnu
    | správu pre konkrétne pravidlo daného atribútu.
    |
    */

    'custom' => [
        'password' => [
            'confirmed' => 'Heslá sa nezhodujú.',
        ],
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | Nasledujúce jazykové riadky sa používajú na nahradenie zástupného textu
    | atribútu za čitateľnejší názov, napríklad "E-mailová adresa" namiesto
    | "email". Pomáha to vytvoriť prirodzenejšie chybové hlásenia.
    |
    */

    'attributes' => [
        'name' => 'meno',
        'first_name' => 'meno',
        'last_name' => 'priezvisko',
        'email' => 'e-mail',
        'password' => 'heslo',
        'password_confirmation' => 'potvrdenie hesla',

        'company_legal_name' => 'názov firmy',
        'company_id_number' => 'IČO',
        'company_tax_id' => 'DIČ',
        'company_vat_id' => 'IČ DPH',
        'company_address_line_1' => 'adresa',
        'company_address_line_2' => 'doplnok adresy',
        'company_city' => 'mesto',
        'company_postal_code' => 'PSČ',
        'company_region' => 'kraj',
        'company_country' => 'krajina',
        'company_email' => 'e-mail firmy',
        'company_phone' => 'telefón firmy',
        'company_website' => 'webová stránka firmy',

        'invite_email' => 'e-mail administrátora',
    ],

];