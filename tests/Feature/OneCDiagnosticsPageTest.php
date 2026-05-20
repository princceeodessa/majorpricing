<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OneCDiagnosticsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_one_c_diagnostics_page(): void
    {
        config()->set('integrations.one_c.username', 'site-exchange');
        config()->set('integrations.one_c.password', 'secret-1c');

        $admin = User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.onec.show'));

        $response->assertOk();
        $response->assertSee('Диагностика 1С');
        $response->assertSee(route('onec.exchange'));
        $response->assertSee('site-exchange');
    }

    public function test_regular_user_cannot_open_one_c_diagnostics_page(): void
    {
        $user = User::factory()->create([
            'is_manager' => false,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('admin.onec.show'))
            ->assertForbidden();
    }

    public function test_manager_cannot_open_one_c_diagnostics_page(): void
    {
        $manager = User::factory()->create([
            'is_manager' => true,
            'is_admin' => false,
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->get(route('admin.onec.show'))
            ->assertForbidden();
    }

    public function test_admin_can_import_catalog_from_received_one_c_package(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
        ]);

        $sessionKey = 'session-catalog-test';
        $basePath = 'one-c-exchange/'.$sessionKey.'/catalog';

        Storage::disk('local')->put($basePath.'/import.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<КоммерческаяИнформация ВерсияСхемы="2.10" ДатаФормирования="2026-04-07T11:00:00">
  <Классификатор>
    <Группы>
      <Группа>
        <Ид>group-root</Ид>
        <Наименование>Профиля</Наименование>
      </Группа>
    </Группы>
  </Классификатор>
  <Каталог>
    <Товары>
      <Товар>
        <Ид>product-guid-1</Ид>
        <Артикул>PF-001</Артикул>
        <Наименование>Профиль M</Наименование>
        <НаименованиеДляПечати>Профиль M печать</НаименованиеДляПечати>
        <Группы>
          <Ид>group-root</Ид>
        </Группы>
      </Товар>
    </Товары>
  </Каталог>
</КоммерческаяИнформация>
XML);

        Storage::disk('local')->put($basePath.'/offers.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<КоммерческаяИнформация ВерсияСхемы="2.10" ДатаФормирования="2026-04-07T11:01:00">
  <ПакетПредложений>
    <ТипыЦен>
      <ТипЦены>
        <Ид>price-type-1</Ид>
        <Наименование>Цена 1</Наименование>
      </ТипЦены>
    </ТипыЦен>
    <Предложения>
      <Предложение>
        <Ид>product-guid-1</Ид>
        <Цены>
          <Цена>
            <ИдТипаЦены>price-type-1</ИдТипаЦены>
            <ЦенаЗаЕдиницу>530.00</ЦенаЗаЕдиницу>
          </Цена>
        </Цены>
      </Предложение>
    </Предложения>
  </ПакетПредложений>
</КоммерческаяИнформация>
XML);

        $this->actingAs($admin)
            ->post(route('admin.onec.catalog.import'), [
                'session_key' => $sessionKey,
            ])
            ->assertRedirect(route('admin.onec.show'));

        $this->assertDatabaseHas('categories', [
            'one_c_id' => 'group-root',
            'name' => 'Профиля',
        ]);

        $this->assertDatabaseHas('products', [
            'one_c_id' => 'product-guid-1',
            'title' => 'Профиль M печать',
            'vendor_code' => 'PF-001',
        ]);

        $product = Product::query()->where('one_c_id', 'product-guid-1')->firstOrFail();

        $this->assertSame(530.0, (float) $product->price_from);
    }

    public function test_diagnostics_page_marks_offers_only_package_as_incomplete(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
        ]);

        $sessionKey = 'session-offers-only';
        $basePath = 'one-c-exchange/'.$sessionKey.'/catalog';

        Storage::disk('local')->put($basePath.'/offers.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<РљРѕРјРјРµСЂС‡РµСЃРєР°СЏРРЅС„РѕСЂРјР°С†РёСЏ Р’РµСЂСЃРёСЏРЎС…РµРјС‹="2.10" Р”Р°С‚Р°Р¤РѕСЂРјРёСЂРѕРІР°РЅРёСЏ="2026-04-07T11:01:00">
  <РџР°РєРµС‚РџСЂРµРґР»РѕР¶РµРЅРёР№ />
</РљРѕРјРјРµСЂС‡РµСЃРєР°СЏРРЅС„РѕСЂРјР°С†РёСЏ>
XML);

        $this->actingAs($admin)
            ->get(route('admin.onec.show'))
            ->assertOk()
            ->assertSee($sessionKey)
            ->assertSee('Нет import.xml');
    }
    public function test_admin_can_preview_received_catalog_file(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
        ]);

        $sessionKey = 'session-preview-test';
        $basePath = 'one-c-exchange/'.$sessionKey.'/catalog';

        Storage::disk('local')->put($basePath.'/offers.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<РљРѕРјРјРµСЂС‡РµСЃРєР°СЏРРЅС„РѕСЂРјР°С†РёСЏ Р’РµСЂСЃРёСЏРЎС…РµРјС‹="2.10">
  <РџР°РєРµС‚РџСЂРµРґР»РѕР¶РµРЅРёР№>
    <РўРёРїС‹Р¦РµРЅ>
      <РўРёРїР¦РµРЅС‹>
        <РРґ>price-type-1</РРґ>
        <РќР°РёРјРµРЅРѕРІР°РЅРёРµ>Р¦РµРЅР° 1</РќР°РёРјРµРЅРѕРІР°РЅРёРµ>
      </РўРёРїС‹Р¦РµРЅ>
    </РўРёРїС‹Р¦РµРЅ>
    <РџСЂРµРґР»РѕР¶РµРЅРёСЏ>
      <РџСЂРµРґР»РѕР¶РµРЅРёРµ>
        <РРґ>product-guid-1</РРґ>
        <Р¦РµРЅС‹>
          <Р¦РµРЅР°>
            <РРґРўРёРїР°Р¦РµРЅС‹>price-type-1</РРґРўРёРїР°Р¦РµРЅС‹>
            <Р¦РµРЅР°Р—Р°Р•РґРёРЅРёС†Сѓ>530.00</Р¦РµРЅР°Р—Р°Р•РґРёРЅРёС†Сѓ>
          </Р¦РµРЅР°>
        </Р¦РµРЅС‹>
      </РџСЂРµРґР»РѕР¶РµРЅРёРµ>
    </РџСЂРµРґР»РѕР¶РµРЅРёСЏ>
  </РџР°РєРµС‚РџСЂРµРґР»РѕР¶РµРЅРёР№>
</РљРѕРјРјРµСЂС‡РµСЃРєР°СЏРРЅС„РѕСЂРјР°С†РёСЏ>
XML);

        $this->actingAs($admin)
            ->get(route('admin.onec.catalog.file', [
                'session_key' => $sessionKey,
                'filename' => 'offers.xml',
            ]))
            ->assertOk()
            ->assertSee($sessionKey)
            ->assertSee('offers.xml')
            ->assertSee('530.00')
            ->assertSee('product-guid-1');
    }

    public function test_admin_can_download_received_catalog_file(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
        ]);

        $sessionKey = 'session-download-file';
        $basePath = 'one-c-exchange/'.$sessionKey.'/catalog';

        Storage::disk('local')->put($basePath.'/import.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<КоммерческаяИнформация>
  <Каталог />
</КоммерческаяИнформация>
XML);

        $response = $this->actingAs($admin)->get(route('admin.onec.catalog.file.download', [
            'session_key' => $sessionKey,
            'filename' => 'import.xml',
        ]));

        $response->assertOk();
        $response->assertHeader('content-disposition', 'attachment; filename=import.xml');
    }

    public function test_admin_can_download_catalog_package_archive(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
        ]);

        $sessionKey = 'session-download-package';
        $basePath = 'one-c-exchange/'.$sessionKey.'/catalog';

        Storage::disk('local')->put($basePath.'/import.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<КоммерческаяИнформация>
  <Каталог />
</КоммерческаяИнформация>
XML);

        Storage::disk('local')->put($basePath.'/offers.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<КоммерческаяИнформация>
  <ПакетПредложений />
</КоммерческаяИнформация>
XML);

        $response = $this->actingAs($admin)->get(route('admin.onec.catalog.package.download', [
            'session_key' => $sessionKey,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/zip');
        $response->assertHeader('content-disposition', 'attachment; filename=onec-catalog-'.$sessionKey.'.zip');
    }
}
