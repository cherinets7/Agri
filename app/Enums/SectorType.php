<?php

namespace App\Enums;

enum SectorType: string
{
    case Irrigation = 'irrigation';
    case Belg = 'belg';
    case Livestock = 'livestock';
    case Fishery = 'fishery';
    case NaturalResource = 'natural_resource';
    case CoffeeSpice = 'coffee_spice';
    case DemoFattening = 'demo_fattening';

    public function label(): string
    {
        return match ($this) {
            self::Irrigation => 'መስኖ',
            self::Belg => 'በልግ ሰብሎች',
            self::Livestock => 'እንስሳት',
            self::Fishery => 'ዓሳ',
            self::NaturalResource => 'ተፈጥሮ ሀብት',
            self::CoffeeSpice => 'ቡናና ቅመማቅመም',
            self::DemoFattening => 'ድለባና ሙከታ',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Irrigation => 'የመስኖ ምንጭ፣ የለማ መሬት (ሄ/ር)፣ የውሃ ተቋማት እና ተጠቃሚ አርሶ አደሮች',
            self::Belg => 'የበልግ ወቅት በቆሎ፣ አተክልትና ስንዴ (ሄ/ር) እና የሚጠበቅ ምርት',
            self::Livestock => 'ዝርያ ማሻሻል (AI)፣ የተወለዱ ጥጆች፣ ክትባት፣ ወተት ምርት እና መኖ ልማት',
            self::Fishery => 'ንቁ የዓሳ ጉሬዎች፣ የተለቀቁ ጫጩቶች እና የተመረተ ዓሳ (ኪ/ግ)',
            self::NaturalResource => 'እርከን ሥራ (ኪ/ሜ)፣ ችግኝ ተከላ፣ የተከለለ ተፋሰስ (ሄ/ር) እና ኮምፖስት',
            self::CoffeeSpice => 'የቡና ችግኝ፣ በርበሬ፣ ኮረሪማ እና ሌሎች ቅመማቅመም ልማት',
            self::DemoFattening => 'የዳልጋ ከብትና በግ/ፍየል ማድለብ፣ የማሳ ማሳያ እና የሙከራ ውጤቶች',
        };
    }

    /** @return array<string, array<string, mixed>> */
    public function fields(): array
    {
        return match ($this) {
            self::Irrigation => [
                'irrigation_source' => ['label' => 'የመስኖ ምንጭ', 'type' => 'select', 'options' => ['river' => 'ወንዝ', 'spring' => 'ምንጭ', 'pond' => 'ኩሬ / ሆረየ', 'well' => 'ጉድጓድ / ፓምፕ'], 'required' => true],
                'water_schemes' => ['label' => 'የመስኖ ተቋማት ብዛት', 'type' => 'int', 'required' => true, 'sum' => true],
                'irrigated_ha' => ['label' => 'በመስኖ የለማ መሬት (ሄ/ር)', 'type' => 'decimal', 'required' => true, 'sum' => true],
                'beneficiaries_male' => ['label' => 'ተጠቃሚ ወንድ', 'type' => 'int', 'required' => true, 'sum' => true],
                'beneficiaries_female' => ['label' => 'ተጠቃሚ ሴት', 'type' => 'int', 'required' => true, 'sum' => true],
                'beneficiaries_total' => ['label' => 'ጠቅላላ ተጠቃሚ', 'type' => 'int', 'computed' => true, 'sum' => true],
            ],
            self::Belg => [
                'maize_ha' => ['label' => 'በልግ በቆሎ (ሄ/ር)', 'type' => 'decimal', 'required' => true, 'sum' => true],
                'vegetables_ha' => ['label' => 'በልግ አተክልት (ሄ/ር)', 'type' => 'decimal', 'required' => true, 'sum' => true],
                'wheat_ha' => ['label' => 'በልግ ስንዴ (ሄ/ር)', 'type' => 'decimal', 'required' => true, 'sum' => true],
                'total_ha' => ['label' => 'ጠቅላላ በልግ (ሄ/ር)', 'type' => 'decimal', 'computed' => true, 'sum' => true],
                'expected_yield_quintal' => ['label' => 'የሚጠበቅ ምርት (ኩ/ል)', 'type' => 'decimal', 'required' => true, 'sum' => true],
                'moisture_status' => ['label' => 'የዝናብ/እርጥበት ሁኔታ', 'type' => 'select', 'options' => ['good' => 'ጥሩ', 'moderate' => 'መካከለኛ', 'low' => 'ዝቅተኛ / እጥረት'], 'required' => true],
            ],
            self::Livestock => [
                'ai_inseminations' => ['label' => 'የዝርያ ማሻሻል (AI)', 'type' => 'int', 'required' => true, 'sum' => true],
                'calves_born' => ['label' => 'የተወለዱ ጥጆች', 'type' => 'int', 'required' => true, 'sum' => true],
                'vaccinated_count' => ['label' => 'ክትባት የተሰጣቸው እንስሳት', 'type' => 'int', 'required' => true, 'sum' => true],
                'milk_liters' => ['label' => 'የወተት ምርት (ሊትር)', 'type' => 'decimal', 'required' => true, 'sum' => true],
                'forage_ha' => ['label' => 'የለማ መኖ (ሄ/ር)', 'type' => 'decimal', 'required' => true, 'sum' => true],
            ],
            self::Fishery => [
                'active_ponds' => ['label' => 'ንቁ የዓሳ ጉሬዎች', 'type' => 'int', 'required' => true, 'sum' => true],
                'fingerlings_stocked' => ['label' => 'የተለቀቁ ጫጩት ዓሳዎች', 'type' => 'int', 'required' => true, 'sum' => true],
                'fish_harvest_kg' => ['label' => 'የተመረተ ዓሳ (ኪ/ግ)', 'type' => 'decimal', 'required' => true, 'sum' => true],
                'beneficiary_farmers' => ['label' => 'ተጠቃሚ አርሶ አደሮች', 'type' => 'int', 'required' => true, 'sum' => true],
            ],
            self::NaturalResource => [
                'terracing_km' => ['label' => 'የተሰራ እርከን (ኪ/ሜ)', 'type' => 'decimal', 'required' => true, 'sum' => true],
                'seedlings_planted' => ['label' => 'የተተከለ ችግኝ', 'type' => 'int', 'required' => true, 'sum' => true],
                'watershed_ha' => ['label' => 'የተከለለ ተፋሰስ (ሄ/ር)', 'type' => 'decimal', 'required' => true, 'sum' => true],
                'compost_tons' => ['label' => 'የተዘጋጀ ኮምፖስት (ቶን)', 'type' => 'decimal', 'required' => true, 'sum' => true],
                'participants_count' => ['label' => 'ተሳታፊ የሰው ኃይል', 'type' => 'int', 'required' => true, 'sum' => true],
            ],
            self::CoffeeSpice => [
                'coffee_seedlings' => ['label' => 'የቡና ችግኝ', 'type' => 'int', 'required' => true, 'sum' => true],
                'coffee_ha' => ['label' => 'የቡና ማሳ (ሄ/ር)', 'type' => 'decimal', 'required' => true, 'sum' => true],
                'berbere_ha' => ['label' => 'በርበሬ (ሄ/ር)', 'type' => 'decimal', 'required' => true, 'sum' => true],
                'korarima_ha' => ['label' => 'ኮረሪማ (ሄ/ር)', 'type' => 'decimal', 'required' => true, 'sum' => true],
                'other_spice_ha' => ['label' => 'ሌሎች ቅመማቅመም (ሄ/ር)', 'type' => 'decimal', 'required' => false, 'sum' => true],
                'rehabilitated_ha' => ['label' => 'የታደሰ የቡና ማሳ (ሄ/ር)', 'type' => 'decimal', 'required' => true, 'sum' => true],
            ],
            self::DemoFattening => [
                'cattle_fattened' => ['label' => 'የዳልጋ ከብት ማድለብ', 'type' => 'int', 'required' => true, 'sum' => true],
                'shoat_fattened' => ['label' => 'በግ/ፍየል ማድለብ', 'type' => 'int', 'required' => true, 'sum' => true],
                'demo_plots_count' => ['label' => 'የማሳ ማሳያ/ሙከራ ቦታዎች', 'type' => 'int', 'required' => true, 'sum' => true],
                'trial_participants' => ['label' => 'ተሳታፊ አርሶ አደሮች', 'type' => 'int', 'required' => true, 'sum' => true],
                'trial_outcome' => ['label' => 'የሙከራ ውጤት / ልምድ', 'type' => 'text', 'required' => false],
            ],
        };
    }

    /** @return array<string, array<string, mixed>> */
    public function summableFields(): array
    {
        return array_filter($this->fields(), fn (array $f) => ! empty($f['sum']));
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
