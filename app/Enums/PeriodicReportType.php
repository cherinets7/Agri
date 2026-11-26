<?php

namespace App\Enums;

enum PeriodicReportType: string
{
    case CropArea = 'crop_area';
    case FarmerCounts = 'farmer_counts';
    case FertilizerWarehouse = 'fertilizer_warehouse';
    case AgriStaff = 'agri_staff';
    case FarmerTraining = 'farmer_training';
    case ZoneInputs = 'zone_inputs';
    case SeedSupply = 'seed_supply';

    public function number(): int
    {
        return match ($this) {
            self::CropArea => 2,
            self::FarmerCounts => 3,
            self::FertilizerWarehouse => 4,
            self::AgriStaff => 5,
            self::FarmerTraining => 6,
            self::ZoneInputs => 7,
            self::SeedSupply => 8,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::CropArea => 'የቆዳ ስፋት በሰብል',
            self::FarmerCounts => 'የአርሶ አደር ብዛት',
            self::FertilizerWarehouse => 'ማዳበሪያ መጋዘን',
            self::AgriStaff => 'የግብርና ሰራተኞች',
            self::FarmerTraining => 'የገበሬዎች ማሰልጠኛ (FTC)',
            self::ZoneInputs => 'ከዞን የሚወርዱ ግብአቶች',
            self::SeedSupply => 'የዘር ማቅረቢያ',
        };
    }

    public function unit(): string
    {
        return match ($this) {
            self::CropArea => 'ሄክታር (ሄ/ር)',
            self::FarmerCounts, self::AgriStaff, self::FarmerTraining => 'ቁጥር',
            self::FertilizerWarehouse, self::SeedSupply => 'ኩንታል (ኩ/ል)',
            self::ZoneInputs => 'ኩንታል / ሊትር / ቁጥር',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CropArea => 'በቆሎ፣ ስንዴ፣ ገብስ እና ሌሎች ሰብሎች የተሸፈነ የቆዳ ስፋት (ሄ/ር)',
            self::FarmerCounts => 'ጠቅላላ ቤተሰብ፣ ዘር የዘሩ/ያልዘሩ፣ ሴት እና ወጣት (18–35) አርሶ አደሮች',
            self::FertilizerWarehouse => 'በመጋዘን የነበረ ክምችት፣ የተከፋፈለ እና የተረፈ ማዳበሪያ (ኩ/ል)',
            self::AgriStaff => 'በዲግሪ፣ በዲፕሎማ እና በዘርፍ የተመደቡ የግብርና ባለሙያዎች ብዛት',
            self::FarmerTraining => 'የገበሬዎች ማሰልጠኛ ማዕከላት (FTC)፣ ክፍለ ጊዜያት እና ሰልጣኝ አርሶ አደሮች',
            self::ZoneInputs => 'ከዞን → ወረዳ → ቀበሌ → አርሶ አደር የደረሱ ግብአቶች ፍሰት',
            self::SeedSupply => 'የቀረበ ምርጥ ዘር (በቆሎ፣ ስንዴ፣ ገብስ) በኩንታል እና ተጠቃሚ አርሶ አደሮች',
        };
    }

    /**
     * Schema of payload fields for form rendering, aggregation, and Excel export.
     * Each field: label, type (int|decimal|select|text), required, options?, computed?, sum?
     *
     * @return array<string, array<string, mixed>>
     */
    public function fields(): array
    {
        return match ($this) {
            self::CropArea => [
                'season' => ['label' => 'ወቅት', 'type' => 'select', 'options' => ['meher' => 'መኸር', 'belg' => 'በልግ', 'irrigation' => 'መስኖ'], 'required' => true],
                'maize_ha' => ['label' => 'በቆሎ (ሄ/ር)', 'type' => 'decimal', 'required' => true, 'sum' => true],
                'wheat_ha' => ['label' => 'ስንዴ (ሄ/ር)', 'type' => 'decimal', 'required' => true, 'sum' => true],
                'barley_ha' => ['label' => 'ገብስ (ሄ/ር)', 'type' => 'decimal', 'required' => true, 'sum' => true],
                'other_ha' => ['label' => 'ሌሎች ሰብሎች (ሄ/ር)', 'type' => 'decimal', 'required' => false, 'sum' => true],
                'total_ha' => ['label' => 'ጠቅላላ (ሄ/ር)', 'type' => 'decimal', 'computed' => true, 'sum' => true],
            ],
            self::FarmerCounts => [
                'households_total' => ['label' => 'ጠቅላላ ቤተሰብ', 'type' => 'int', 'required' => true, 'sum' => true],
                'sowed_farmers' => ['label' => 'ዘር የዘሩ', 'type' => 'int', 'required' => true, 'sum' => true],
                'unsowed_farmers' => ['label' => 'ዘር ያልዘሩ', 'type' => 'int', 'required' => true, 'sum' => true],
                'female_farmers' => ['label' => 'ሴት አርሶ አደሮች', 'type' => 'int', 'required' => true, 'sum' => true],
                'youth_farmers' => ['label' => 'ወጣት (18–35)', 'type' => 'int', 'required' => true, 'sum' => true],
            ],
            self::FertilizerWarehouse => [
                'fertilizer_type' => ['label' => 'የማዳበሪያ ዓይነት', 'type' => 'select', 'options' => ['nps' => 'ኤን.ፒ.ኤስ (NPS)', 'urea' => 'ዩሪያ (Urea)', 'combined' => 'ጥምር (NPS + Urea)'], 'required' => true],
                'stock_quintal' => ['label' => 'ክምችት (ኩ/ል)', 'type' => 'decimal', 'required' => true, 'sum' => true],
                'distributed_quintal' => ['label' => 'ተከፋፈለ (ኩ/ል)', 'type' => 'decimal', 'required' => true, 'sum' => true],
                'remaining_quintal' => ['label' => 'ተረፈ (ኩ/ል)', 'type' => 'decimal', 'required' => true, 'sum' => true],
            ],
            self::AgriStaff => [
                'sector' => ['label' => 'ዘርፍ', 'type' => 'select', 'options' => config('extension.service_types') + ['all' => 'ሁሉም ዘርፎች'], 'required' => true],
                'degree_count' => ['label' => 'ዲግሪ', 'type' => 'int', 'required' => true, 'sum' => true],
                'diploma_count' => ['label' => 'ዲፕሎማ', 'type' => 'int', 'required' => true, 'sum' => true],
                'other_count' => ['label' => 'ሌላ / ሰርተፍኬት', 'type' => 'int', 'required' => false, 'sum' => true],
                'female_staff' => ['label' => 'ሴት ሰራተኞች', 'type' => 'int', 'required' => true, 'sum' => true],
                'total_staff' => ['label' => 'ጠቅላላ ሰራተኞች', 'type' => 'int', 'computed' => true, 'sum' => true],
            ],
            self::FarmerTraining => [
                'ftc_count' => ['label' => 'የFTC ብዛት', 'type' => 'int', 'required' => true, 'sum' => true],
                'sessions_count' => ['label' => 'የስልጠና ክፍለ ጊዜ', 'type' => 'int', 'required' => true, 'sum' => true],
                'trainees_total' => ['label' => 'ጠቅላላ ሰልጣኞች', 'type' => 'int', 'required' => true, 'sum' => true],
                'female_trainees' => ['label' => 'ሴት ሰልጣኞች', 'type' => 'int', 'required' => true, 'sum' => true],
                'youth_trainees' => ['label' => 'ወጣት (18–35)', 'type' => 'int', 'required' => true, 'sum' => true],
                'topic' => ['label' => 'የስልጠና ርዕስ', 'type' => 'text', 'required' => false],
            ],
            self::ZoneInputs => [
                'input_type' => ['label' => 'የግብዓት ዓይነት', 'type' => 'select', 'options' => self::inputTypes(), 'required' => true],
                'unit' => ['label' => 'መለኪያ', 'type' => 'select', 'options' => ['quintal' => 'ኩ/ል', 'liter' => 'ሊትር', 'count' => 'ቁጥር'], 'required' => true],
                'woreda_dispatched' => ['label' => 'ከወረዳ የተላከ', 'type' => 'decimal', 'required' => true, 'sum' => true],
                'kebele_received' => ['label' => 'ቀበሌ የተቀበለው', 'type' => 'decimal', 'required' => true, 'sum' => true],
                'farmer_distributed' => ['label' => 'ለአርሶ አደር የደረሰ', 'type' => 'decimal', 'required' => true, 'sum' => true],
                'beneficiary_farmers' => ['label' => 'ተጠቃሚ አርሶ አደሮች', 'type' => 'int', 'required' => true, 'sum' => true],
            ],
            self::SeedSupply => [
                'maize_quintal' => ['label' => 'በቆሎ (ኩ/ል)', 'type' => 'decimal', 'required' => true, 'sum' => true],
                'wheat_quintal' => ['label' => 'ስንዴ (ኩ/ል)', 'type' => 'decimal', 'required' => true, 'sum' => true],
                'barley_quintal' => ['label' => 'ገብስ (ኩ/ል)', 'type' => 'decimal', 'required' => true, 'sum' => true],
                'other_quintal' => ['label' => 'ሌላ ዘር (ኩ/ል)', 'type' => 'decimal', 'required' => false, 'sum' => true],
                'total_quintal' => ['label' => 'ጠቅላላ ዘር (ኩ/ል)', 'type' => 'decimal', 'computed' => true, 'sum' => true],
                'beneficiary_farmers' => ['label' => 'ተጠቃሚ አርሶ አደሮች', 'type' => 'int', 'required' => true, 'sum' => true],
            ],
        };
    }

    /** @return array<string, array<string, mixed>> */
    public function summableFields(): array
    {
        return array_filter($this->fields(), fn (array $f) => ! empty($f['sum']));
    }

    /** @return array<string, string> */
    public static function inputTypes(): array
    {
        return [
            'fertilizer' => 'ማዳበሪያ',
            'improved_seed' => 'ምርጥ ዘር',
            'pesticide' => 'ፀረ-ተባይ / ኬሚካል',
            'equipment' => 'የእርሻ መሳሪያ',
            'other' => 'ሌላ ግብዓት',
        ];
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => "#{$t->number()} {$t->label()}"])->all();
    }
}
