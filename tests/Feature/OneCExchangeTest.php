<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\OneC\OneCCatalogExchangeService;
use App\Services\OneC\OneCExchangeStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class OneCExchangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_c_checkauth_and_init_work_without_custom_1c_code(): void
    {
        config()->set('integrations.one_c.username', 'site-exchange');
        config()->set('integrations.one_c.password', 'secret-1c');
        config()->set('integrations.one_c.file_limit', 5242880);

        $response = $this
            ->withServerVariables($this->oneCServer())
            ->get('/1c/exchange?type=catalog&mode=checkauth');

        $response->assertOk();
        $this->assertStringStartsWith("success\n", $response->getContent());

        $cookie = $this->extractSessionCookie($response);

        $initResponse = $this
            ->withServerVariables($this->oneCServer())
            ->withCookie($cookie->getName(), $cookie->getValue())
            ->get('/1c/exchange?type=catalog&mode=init');

        $initResponse->assertOk();
        $this->assertStringContainsString('zip=no', $initResponse->getContent());
        $this->assertStringContainsString('file_limit=5242880', $initResponse->getContent());
    }

    public function test_one_c_catalog_import_creates_categories_products_and_prices(): void
    {
        config()->set('integrations.one_c.username', 'site-exchange');
        config()->set('integrations.one_c.password', 'secret-1c');

        $cookie = $this->authenticateOneC();

        $this->withServerVariables($this->oneCServer())
            ->withCookie($cookie->getName(), $cookie->getValue())
            ->get('/1c/exchange?type=catalog&mode=init')
            ->assertOk();

        $this->postExchangeFile($cookie, 'catalog', 'import.xml', $this->catalogImportXml())->assertOk();
        $this->postExchangeFile($cookie, 'catalog', 'offers.xml', $this->catalogOffersXml())->assertOk();

        $this->withServerVariables($this->oneCServer())
            ->withCookie($cookie->getName(), $cookie->getValue())
            ->get('/1c/exchange?type=catalog&mode=import&filename=import.xml')
            ->assertOk();

        $this->assertDatabaseHas('categories', [
            'one_c_id' => 'group-child',
            'name' => 'Стеновые',
        ]);

        $product = Product::query()->where('one_c_id', 'product-guid-1')->firstOrFail();

        $this->assertSame('PF-001', $product->vendor_code);
        $this->assertSame('Профиль M печать', $product->title);
        $this->assertSame('product-code-1', $product->one_c_code);
        $this->assertSame('TOREC', $product->brand_name);
        $this->assertSame('Актуальное описание из дополнительного реквизита', $product->description);
        $this->assertSame(530.0, (float) $product->price_from);
        $this->assertDatabaseHas('product_prices', [
            'product_id' => $product->id,
            'column_index' => 1,
            'label' => 'Цена 1',
        ]);
        $this->assertDatabaseHas('one_c_price_types', [
            'one_c_id' => 'price-type-1',
            'column_index' => 1,
        ]);
    }

    public function test_one_c_repeated_init_does_not_delete_catalog_before_offers_import(): void
    {
        $sessionKey = 'catalog-session-test';
        $storage = app(OneCExchangeStorage::class);
        $service = app(OneCCatalogExchangeService::class);

        $storage->clearType($sessionKey, 'catalog');

        try {
            $storage->resetUploadState($sessionKey, 'catalog');
            $storage->appendFile($sessionKey, 'catalog', 'import.xml', $this->catalogImportXml());

            $this->assertNotNull($storage->resolveUploadedPath($sessionKey, 'catalog', 'import.xml'));

            // A second init is how 1C switches from import.xml to offers.xml.
            $storage->resetUploadState($sessionKey, 'catalog');

            $this->assertNotNull($storage->resolveUploadedPath($sessionKey, 'catalog', 'import.xml'));

            $storage->appendFile($sessionKey, 'catalog', 'offers.xml', $this->catalogOffersXml());

            $result = $service->import($sessionKey);

            $this->assertTrue($result['has_import']);
            $this->assertTrue($result['has_offers']);
            $this->assertSame(1, Product::query()->where('one_c_id', 'product-guid-1')->count());

            $product = Product::query()->where('one_c_id', 'product-guid-1')->firstOrFail();

            $this->assertSame('Профиль M печать', $product->title);
            $this->assertSame('product-code-1', $product->one_c_code);
            $this->assertSame('TOREC', $product->brand_name);
            $this->assertSame(530.0, (float) $product->price_from);
            $this->assertDatabaseHas('categories', [
                'one_c_id' => 'group-child',
                'name' => 'Стеновые',
            ]);
            $this->assertDatabaseHas('product_prices', [
                'product_id' => $product->id,
                'column_index' => 1,
                'label' => 'Цена 1',
            ]);
        } finally {
            $storage->clearType($sessionKey, 'catalog');
        }
    }

    public function test_one_c_catalog_import_uses_full_name_requisite_as_public_title_when_print_title_is_missing(): void
    {
        $sessionKey = 'catalog-full-name-space';
        $storage = app(OneCExchangeStorage::class);
        $service = app(OneCCatalogExchangeService::class);

        $storage->clearType($sessionKey, 'catalog');

        try {
            $storage->resetUploadState($sessionKey, 'catalog');
            $storage->appendFile($sessionKey, 'catalog', 'import.xml', $this->catalogImportXmlWithFullNameSpaceRequisite());
            $storage->appendFile($sessionKey, 'catalog', 'offers.xml', $this->catalogOffersXml());

            $service->import($sessionKey);

            $this->assertDatabaseHas('products', [
                'one_c_id' => 'product-guid-1',
                'title' => 'Профиль M печать',
            ]);
        } finally {
            $storage->clearType($sessionKey, 'catalog');
        }
    }

    public function test_one_c_catalog_import_does_not_use_file_description_requisite_as_product_description(): void
    {
        $sessionKey = 'catalog-file-description';
        $storage = app(OneCExchangeStorage::class);
        $service = app(OneCCatalogExchangeService::class);

        $storage->clearType($sessionKey, 'catalog');

        try {
            $storage->resetUploadState($sessionKey, 'catalog');
            $storage->appendFile($sessionKey, 'catalog', 'import.xml', $this->catalogImportXmlWithFileDescriptionOnly());

            $service->import($sessionKey);

            $product = Product::query()->where('one_c_id', 'product-file-description')->firstOrFail();

            $this->assertNull($product->description);
            $this->assertSame('PF-FILE', $product->vendor_code);
        } finally {
            $storage->clearType($sessionKey, 'catalog');
        }
    }

    public function test_one_c_import_merges_existing_excel_catalog_records_instead_of_creating_duplicates(): void
    {
        config()->set('integrations.one_c.username', 'site-exchange');
        config()->set('integrations.one_c.password', 'secret-1c');

        $legacyRoot = Category::query()->create([
            'name' => 'Alform',
            'slug' => 'alform',
            'source_sheet' => 'ALFORM',
            'sort_order' => 0,
            'accent_color' => '#d11117',
        ]);

        $legacyChild = Category::query()->create([
            'parent_id' => $legacyRoot->id,
            'name' => 'Profiles',
            'slug' => 'alform-profiles',
            'source_sheet' => 'ALFORM',
            'sort_order' => 0,
            'accent_color' => '#d11117',
        ]);

        $duplicateRoot = Category::query()->create([
            'name' => 'ALFORM',
            'slug' => 'alform-1c',
            'one_c_id' => 'group-root-merge',
            'source_sheet' => '1C',
            'sort_order' => 0,
            'accent_color' => '#d11117',
        ]);

        $duplicateChild = Category::query()->create([
            'parent_id' => $duplicateRoot->id,
            'name' => 'PROFILES',
            'slug' => 'profiles-1c',
            'one_c_id' => 'group-child-merge',
            'source_sheet' => '1C',
            'sort_order' => 0,
            'accent_color' => '#d11117',
        ]);

        $legacyProduct = Product::query()->create([
            'category_id' => $legacyChild->id,
            'title' => 'Air Kraab 2.0',
            'name' => 'Air Kraab 2.0',
            'slug' => 'air-kraab-2-0',
            'source_sheet' => 'ALFORM',
            'source_row' => 12,
            'image_path' => 'catalog-media/excel/air-kraab.jpg',
            'sort_order' => 0,
        ]);

        $duplicateProduct = Product::query()->create([
            'category_id' => $duplicateChild->id,
            'title' => 'Air Kraab 2.0',
            'name' => 'Air Kraab 2.0',
            'slug' => 'air-kraab-2-0-1c',
            'one_c_id' => 'product-merge-1',
            'source_sheet' => '1C',
            'sort_order' => 0,
        ]);

        $duplicateProduct->prices()->create([
            'column_index' => 1,
            'label' => 'Old 1C',
            'display_value' => '99,00',
            'min_amount' => 99.0,
        ]);

        $storage = app(OneCExchangeStorage::class);
        $service = app(OneCCatalogExchangeService::class);

        $storage->clearType('merge-session', 'catalog');
        $storage->resetUploadState('merge-session', 'catalog');
        $storage->appendFile('merge-session', 'catalog', 'import.xml', $this->mergingCatalogImportXmlFixed());
        $storage->appendFile('merge-session', 'catalog', 'offers.xml', $this->mergingCatalogOffersXmlFixed());
        $service->import('merge-session');

        $legacyRoot->refresh();
        $legacyChild->refresh();
        $legacyProduct->refresh();

        $this->assertSame(2, Category::count());
        $this->assertSame(1, Product::count());
        $this->assertSame('group-root-merge', $legacyRoot->one_c_id);
        $this->assertSame('group-child-merge', $legacyChild->one_c_id);
        $this->assertSame($legacyRoot->id, $legacyChild->parent_id);
        $this->assertSame('product-merge-1', $legacyProduct->one_c_id);
        $this->assertSame($legacyChild->id, $legacyProduct->category_id);
        $this->assertSame('catalog-media/excel/air-kraab.jpg', $legacyProduct->image_path);
        $this->assertSame('AK-200', $legacyProduct->vendor_code);
        $this->assertSame(730.0, (float) $legacyProduct->price_from);
        $this->assertDatabaseMissing('categories', ['id' => $duplicateRoot->id]);
        $this->assertDatabaseMissing('categories', ['id' => $duplicateChild->id]);
        $this->assertDatabaseMissing('products', ['id' => $duplicateProduct->id]);
        $this->assertDatabaseHas('product_prices', [
            'product_id' => $legacyProduct->id,
            'column_index' => 1,
            'min_amount' => 730.0,
        ]);
    }

    public function test_one_c_sale_query_exports_orders_and_success_marks_them_sent(): void
    {
        config()->set('integrations.one_c.username', 'site-exchange');
        config()->set('integrations.one_c.password', 'secret-1c');

        $user = User::factory()->create([
            'company' => 'ООО Объект',
        ]);

        $category = Category::query()->create([
            'name' => 'Профиля',
            'slug' => 'profiliya',
            'one_c_id' => 'group-child',
            'sort_order' => 0,
            'accent_color' => '#d11117',
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Профиль M',
            'name' => 'Профиль M',
            'slug' => 'profil-m',
            'one_c_id' => 'product-guid-1',
            'vendor_code' => 'PF-001',
            'sort_order' => 0,
        ]);

        $order = Order::query()->create([
            'user_id' => $user->id,
            'number' => 'ORD-20260407-00001',
            'status' => 'new',
            'payment_status' => 'pending',
            'customer_name' => 'Иван Клиент',
            'customer_company' => 'ООО Объект',
            'customer_email' => 'client@example.com',
            'customer_phone' => '+79991112233',
            'customer_delivery_address' => 'Саратов, Тестовая 1',
            'items_count' => 1,
            'subtotal_amount' => 1060,
            'total_amount' => 1060,
            'price_profile_name' => 'Цена 1',
            'placed_at' => now(),
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_title' => $product->title,
            'product_slug' => $product->slug,
            'quantity' => 2,
            'price_label' => 'Цена 1',
            'unit_price' => 530,
            'line_total' => 1060,
            'measurement_value' => 'шт',
        ]);

        $cookie = $this->authenticateOneC();

        $queryResponse = $this
            ->withServerVariables($this->oneCServer())
            ->withCookie($cookie->getName(), $cookie->getValue())
            ->get('/1c/exchange?type=sale&mode=query');

        $queryResponse->assertOk();
        $queryResponse->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $this->assertStringContainsString('<КоммерческаяИнформация', $queryResponse->getContent());
        $this->assertStringContainsString('ORD-20260407-00001', $queryResponse->getContent());
        $this->assertStringContainsString('product-guid-1', $queryResponse->getContent());

        $this->withServerVariables($this->oneCServer())
            ->withCookie($cookie->getName(), $cookie->getValue())
            ->post('/1c/exchange?type=sale&mode=success')
            ->assertOk();

        $this->assertNotNull($order->fresh()->one_c_exported_at);
    }

    private function authenticateOneC(): Cookie
    {
        $response = $this
            ->withServerVariables($this->oneCServer())
            ->get('/1c/exchange?type=catalog&mode=checkauth');

        $response->assertOk();

        return $this->extractSessionCookie($response);
    }

    private function postExchangeFile(Cookie $cookie, string $type, string $filename, string $content)
    {
        return $this->withServerVariables($this->oneCServer())
            ->withCookie($cookie->getName(), $cookie->getValue())
            ->call(
                'POST',
                '/1c/exchange',
                [
                    'type' => $type,
                    'mode' => 'file',
                    'filename' => $filename,
                ],
                [],
                [],
                $this->oneCServer(),
                $content,
            );
    }

    /**
     * @return array<string, string>
     */
    private function oneCServer(): array
    {
        return [
            'PHP_AUTH_USER' => 'site-exchange',
            'PHP_AUTH_PW' => 'secret-1c',
        ];
    }

    private function extractSessionCookie($response): Cookie
    {
        $cookieName = (string) config('session.cookie', 'laravel_session');

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $cookieName) {
                return $cookie;
            }
        }

        $this->fail('Session cookie was not returned by checkauth.');
    }

    private function catalogImportXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<КоммерческаяИнформация ВерсияСхемы="2.10" ДатаФормирования="2026-04-07T11:00:00">
  <Классификатор>
    <Ид>classifier-1</Ид>
    <Наименование>Основной каталог</Наименование>
    <Свойства>
      <Свойство>
        <Ид>description-property-id</Ид>
        <Наименование>Описание</Наименование>
        <ТипЗначений>Строка</ТипЗначений>
        <ДляТоваров>true</ДляТоваров>
      </Свойство>
    </Свойства>
    <Группы>
      <Группа>
        <Ид>group-root</Ид>
        <Наименование>Профиля</Наименование>
        <Группы>
          <Группа>
            <Ид>group-child</Ид>
            <Наименование>Стеновые</Наименование>
          </Группа>
        </Группы>
      </Группа>
    </Группы>
  </Классификатор>
  <Каталог>
    <Ид>catalog-1</Ид>
    <Наименование>Каталог MAJOR</Наименование>
    <Товары>
      <Товар>
        <Ид>product-guid-1</Ид>
        <Код>product-code-1</Код>
        <Артикул>PF-001</Артикул>
        <Наименование>Профиль M</Наименование>
        <Описание>Старое неверное основное описание из 1С</Описание>
        <БазоваяЕдиница НаименованиеКраткое="шт">шт</БазоваяЕдиница>
        <ЗначенияСвойств>
          <ЗначенияСвойства>
            <Ид>description-property-id</Ид>
            <Значение>Актуальное описание из дополнительного реквизита</Значение>
          </ЗначенияСвойства>
        </ЗначенияСвойств>
        <ЗначенияРеквизитов>
          <ЗначениеРеквизита>
            <Наименование>НаименованиеДляПечати</Наименование>
            <Значение>Профиль M печать</Значение>
          </ЗначениеРеквизита>
          <ЗначениеРеквизита>
            <Наименование>Бренд</Наименование>
            <Значение>TOREC</Значение>
          </ЗначениеРеквизита>
        </ЗначенияРеквизитов>
        <Группы>
          <Ид>group-child</Ид>
        </Группы>
      </Товар>
    </Товары>
  </Каталог>
</КоммерческаяИнформация>
XML;
    }

    private function catalogOffersXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<КоммерческаяИнформация ВерсияСхемы="2.10" ДатаФормирования="2026-04-07T11:01:00">
  <ПакетПредложений>
    <Ид>offers-1</Ид>
    <Наименование>Прайс</Наименование>
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
XML;
    }

    private function catalogImportXmlWithFullNameSpaceRequisite(): string
    {
        return str_replace(
            json_decode('"\u041d\u0430\u0438\u043c\u0435\u043d\u043e\u0432\u0430\u043d\u0438\u0435\u0414\u043b\u044f\u041f\u0435\u0447\u0430\u0442\u0438"', true),
            json_decode('"\u041f\u043e\u043b\u043d\u043e\u0435 \u043d\u0430\u0438\u043c\u0435\u043d\u043e\u0432\u0430\u043d\u0438\u0435"', true),
            $this->catalogImportXml(),
        );
    }

    private function catalogImportXmlWithFileDescriptionOnly(): string
    {
        $commerce = json_decode('"\u041a\u043e\u043c\u043c\u0435\u0440\u0447\u0435\u0441\u043a\u0430\u044f\u0418\u043d\u0444\u043e\u0440\u043c\u0430\u0446\u0438\u044f"', true);
        $classifier = json_decode('"\u041a\u043b\u0430\u0441\u0441\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440"', true);
        $catalog = json_decode('"\u041a\u0430\u0442\u0430\u043b\u043e\u0433"', true);
        $id = json_decode('"\u0418\u0434"', true);
        $name = json_decode('"\u041d\u0430\u0438\u043c\u0435\u043d\u043e\u0432\u0430\u043d\u0438\u0435"', true);
        $groups = json_decode('"\u0413\u0440\u0443\u043f\u043f\u044b"', true);
        $group = json_decode('"\u0413\u0440\u0443\u043f\u043f\u0430"', true);
        $products = json_decode('"\u0422\u043e\u0432\u0430\u0440\u044b"', true);
        $product = json_decode('"\u0422\u043e\u0432\u0430\u0440"', true);
        $code = json_decode('"\u041a\u043e\u0434"', true);
        $vendorCode = json_decode('"\u0410\u0440\u0442\u0438\u043a\u0443\u043b"', true);
        $description = json_decode('"\u041e\u043f\u0438\u0441\u0430\u043d\u0438\u0435"', true);
        $requisiteValues = json_decode('"\u0417\u043d\u0430\u0447\u0435\u043d\u0438\u044f\u0420\u0435\u043a\u0432\u0438\u0437\u0438\u0442\u043e\u0432"', true);
        $requisiteValue = json_decode('"\u0417\u043d\u0430\u0447\u0435\u043d\u0438\u0435\u0420\u0435\u043a\u0432\u0438\u0437\u0438\u0442\u0430"', true);
        $value = json_decode('"\u0417\u043d\u0430\u0447\u0435\u043d\u0438\u0435"', true);
        $printName = json_decode('"\u041d\u0430\u0438\u043c\u0435\u043d\u043e\u0432\u0430\u043d\u0438\u0435\u0414\u043b\u044f\u041f\u0435\u0447\u0430\u0442\u0438"', true);
        $fileDescription = json_decode('"\u041e\u043f\u0438\u0441\u0430\u043d\u0438\u0435\u0424\u0430\u0439\u043b\u0430"', true);

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<{$commerce}>
  <{$classifier}>
    <{$id}>classifier-file-description</{$id}>
    <{$name}>Catalog</{$name}>
    <{$groups}>
      <{$group}>
        <{$id}>group-file-description</{$id}>
        <{$name}>Profiles</{$name}>
      </{$group}>
    </{$groups}>
  </{$classifier}>
  <{$catalog}>
    <{$id}>catalog-file-description</{$id}>
    <{$name}>Catalog</{$name}>
    <{$products}>
      <{$product}>
        <{$id}>product-file-description</{$id}>
        <{$code}>product-file-code</{$code}>
        <{$vendorCode}>PF-FILE</{$vendorCode}>
        <{$name}>Profile file description</{$name}>
        <{$description}>Stale main description must stay ignored</{$description}>
        <{$requisiteValues}>
          <{$requisiteValue}>
            <{$name}>{$printName}</{$name}>
            <{$value}>Profile file description print title</{$value}>
          </{$requisiteValue}>
          <{$requisiteValue}>
            <{$name}>{$fileDescription}</{$name}>
            <{$value}>import_files/c5/image.png#Gardina P 70 file caption</{$value}>
          </{$requisiteValue}>
        </{$requisiteValues}>
        <{$groups}>
          <{$id}>group-file-description</{$id}>
        </{$groups}>
      </{$product}>
    </{$products}>
  </{$catalog}>
</{$commerce}>
XML;
    }

    private function mergingCatalogImportXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<РљРѕРјРјРµСЂС‡РµСЃРєР°СЏРРЅС„РѕСЂРјР°С†РёСЏ Р’РµСЂСЃРёСЏРЎС…РµРјС‹="2.10" Р”Р°С‚Р°Р¤РѕСЂРјРёСЂРѕРІР°РЅРёСЏ="2026-04-09T10:00:00">
  <РљР»Р°СЃСЃРёС„РёРєР°С‚РѕСЂ>
    <Р“СЂСѓРїРїС‹>
      <Р“СЂСѓРїРїР°>
        <РРґ>group-root-merge</РРґ>
        <РќР°РёРјРµРЅРѕРІР°РЅРёРµ>ALFORM</РќР°РёРјРµРЅРѕРІР°РЅРёРµ>
        <Р“СЂСѓРїРїС‹>
          <Р“СЂСѓРїРїР°>
            <РРґ>group-child-merge</РРґ>
            <РќР°РёРјРµРЅРѕРІР°РЅРёРµ>PROFILES</РќР°РёРјРµРЅРѕРІР°РЅРёРµ>
          </Р“СЂСѓРїРїР°>
        </Р“СЂСѓРїРїС‹>
      </Р“СЂСѓРїРїР°>
    </Р“СЂСѓРїРїС‹>
  </РљР»Р°СЃСЃРёС„РёРєР°С‚РѕСЂ>
  <РљР°С‚Р°Р»РѕРі>
    <РўРѕРІР°СЂС‹>
      <РўРѕРІР°СЂ>
        <РРґ>product-merge-1</РРґ>
        <РљРѕРґ>product-code-merge</РљРѕРґ>
        <РђСЂС‚РёРєСѓР»>AK-200</РђСЂС‚РёРєСѓР»>
        <РќР°РёРјРµРЅРѕРІР°РЅРёРµ>Air Kraab 2.0</РќР°РёРјРµРЅРѕРІР°РЅРёРµ>
        <РћРїРёСЃР°РЅРёРµ>Profile from 1C</РћРїРёСЃР°РЅРёРµ>
        <Р‘Р°Р·РѕРІР°СЏР•РґРёРЅРёС†Р° РќР°РёРјРµРЅРѕРІР°РЅРёРµРљСЂР°С‚РєРѕРµ="pcs">pcs</Р‘Р°Р·РѕРІР°СЏР•РґРёРЅРёС†Р°>
        <Р“СЂСѓРїРїС‹>
          <РРґ>group-child-merge</РРґ>
        </Р“СЂСѓРїРїС‹>
      </РўРѕРІР°СЂ>
    </РўРѕРІР°СЂС‹>
  </РљР°С‚Р°Р»РѕРі>
</РљРѕРјРјРµСЂС‡РµСЃРєР°СЏРРЅС„РѕСЂРјР°С†РёСЏ>
XML;
    }

    private function mergingCatalogOffersXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<РљРѕРјРјРµСЂС‡РµСЃРєР°СЏРРЅС„РѕСЂРјР°С†РёСЏ Р’РµСЂСЃРёСЏРЎС…РµРјС‹="2.10" Р”Р°С‚Р°Р¤РѕСЂРјРёСЂРѕРІР°РЅРёСЏ="2026-04-09T10:01:00">
  <РџР°РєРµС‚РџСЂРµРґР»РѕР¶РµРЅРёР№>
    <РўРёРїС‹Р¦РµРЅ>
      <РўРёРїР¦РµРЅС‹>
        <РРґ>price-type-merge</РРґ>
        <РќР°РёРјРµРЅРѕРІР°РЅРёРµ>Оптовая</РќР°РёРјРµРЅРѕРІР°РЅРёРµ>
      </РўРёРїР¦РµРЅС‹>
    </РўРёРїС‹Р¦РµРЅ>
    <РџСЂРµРґР»РѕР¶РµРЅРёСЏ>
      <РџСЂРµРґР»РѕР¶РµРЅРёРµ>
        <РРґ>product-merge-1</РРґ>
        <Р¦РµРЅС‹>
          <Р¦РµРЅР°>
            <РРґРўРёРїР°Р¦РµРЅС‹>price-type-merge</РРґРўРёРїР°Р¦РµРЅС‹>
            <Р¦РµРЅР°Р—Р°Р•РґРёРЅРёС†Сѓ>730.00</Р¦РµРЅР°Р—Р°Р•РґРёРЅРёС†Сѓ>
          </Р¦РµРЅР°>
        </Р¦РµРЅС‹>
      </РџСЂРµРґР»РѕР¶РµРЅРёРµ>
    </РџСЂРµРґР»РѕР¶РµРЅРёСЏ>
  </РџР°РєРµС‚РџСЂРµРґР»РѕР¶РµРЅРёР№>
</РљРѕРјРјРµСЂС‡РµСЃРєР°СЏРРЅС„РѕСЂРјР°С†РёСЏ>
XML;
    }

    private function mergingCatalogImportXmlFixed(): string
    {
        $idTag = json_decode('"\u0418\u0434"', true);
        $nameTag = json_decode('"\u041d\u0430\u0438\u043c\u0435\u043d\u043e\u0432\u0430\u043d\u0438\u0435"', true);
        $groupTag = json_decode('"\u0413\u0440\u0443\u043f\u043f\u0430"', true);
        $productTag = json_decode('"\u0422\u043e\u0432\u0430\u0440"', true);
        $codeTag = json_decode('"\u041a\u043e\u0434"', true);
        $vendorTag = json_decode('"\u0410\u0440\u0442\u0438\u043a\u0443\u043b"', true);
        $descriptionTag = json_decode('"\u041e\u043f\u0438\u0441\u0430\u043d\u0438\u0435"', true);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadXML($this->catalogImportXml());

        $groups = $dom->getElementsByTagName($groupTag);
        $rootGroup = $groups->item(0);
        $childGroup = $groups->item(1);
        $product = $dom->getElementsByTagName($productTag)->item(0);

        $rootGroup->getElementsByTagName($idTag)->item(0)->nodeValue = 'group-root-merge';
        $rootGroup->getElementsByTagName($nameTag)->item(0)->nodeValue = 'ALFORM';

        $childGroup->getElementsByTagName($idTag)->item(0)->nodeValue = 'group-child-merge';
        $childGroup->getElementsByTagName($nameTag)->item(0)->nodeValue = 'PROFILES';

        $productIds = $product->getElementsByTagName($idTag);
        $productIds->item(0)->nodeValue = 'product-merge-1';
        $productIds->item(1)->nodeValue = 'group-child-merge';
        $product->getElementsByTagName($codeTag)->item(0)->nodeValue = 'product-code-merge';
        $product->getElementsByTagName($vendorTag)->item(0)->nodeValue = 'AK-200';
        $product->getElementsByTagName($nameTag)->item(0)->nodeValue = 'Air Kraab 2.0';
        $product->getElementsByTagName($descriptionTag)->item(0)->nodeValue = 'Profile from 1C';

        return $dom->saveXML();
    }

    private function mergingCatalogOffersXmlFixed(): string
    {
        $idTag = json_decode('"\u0418\u0434"', true);
        $nameTag = json_decode('"\u041d\u0430\u0438\u043c\u0435\u043d\u043e\u0432\u0430\u043d\u0438\u0435"', true);
        $offerTag = json_decode('"\u041f\u0440\u0435\u0434\u043b\u043e\u0436\u0435\u043d\u0438\u0435"', true);
        $priceTypeIdTag = json_decode('"\u0418\u0434\u0422\u0438\u043f\u0430\u0426\u0435\u043d\u044b"', true);
        $amountTag = json_decode('"\u0426\u0435\u043d\u0430\u0417\u0430\u0415\u0434\u0438\u043d\u0438\u0446\u0443"', true);
        $priceTypeTag = json_decode('"\u0422\u0438\u043f\u0426\u0435\u043d\u044b"', true);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadXML($this->catalogOffersXml());

        $priceType = $dom->getElementsByTagName($priceTypeTag)->item(0);
        $offer = $dom->getElementsByTagName($offerTag)->item(0);

        $priceType->getElementsByTagName($idTag)->item(0)->nodeValue = 'price-type-merge';
        $priceType->getElementsByTagName($nameTag)->item(0)->nodeValue = 'Оптовая';
        $offer->getElementsByTagName($idTag)->item(0)->nodeValue = 'product-merge-1';
        $offer->getElementsByTagName($priceTypeIdTag)->item(0)->nodeValue = 'price-type-merge';
        $offer->getElementsByTagName($amountTag)->item(0)->nodeValue = '730.00';

        return $dom->saveXML();
    }
}
