АНГАА АВТО — БРЭНД + CLAUDE CODE BUILD БАГЦ
2026-11-03 · Дархан · Ангаад зориулсан хувилбар 2

НЭР: АНГАА АВТО
ТАЙЛБАР: Авто сервис · Дархан
УРИА: Засвар бүр бүртгэлтэй.
БРЭНДИЙН ГОЛ САНАА: зурагтай тайлбар, урьдчилан зөвшөөрүүлсэн үнэ, дараагийн үйлчилгээний сануулга.

Энэ ZIP-д БОДИТ PNG лого, дан тэмдэг, 2 байрлалтай маскот, PNG brand board, брэндийн дүрэм, design tokens, asset суулгах script, бүтэн Laravel систем хөгжүүлэх шаардлага бий. Laravel application source code хараахан бүтээж тестлээгүй; Claude Code доорх заавраар application-ийг бүтээнэ. Brand preview нь local browser дээр нээх бодит asset gallery, үйлчилгээний ажилладаг сайт биш.

1. ЭХЛЭЭД ХАРАХ ФАЙЛУУД
branding/ANGAA_AUTO_BRAND_BOARD.png — лого, маскот, өнгө, типографи, самбар/хувцасны хэрэглээний нэгдсэн PNG.
branding/BRAND_PREVIEW.html — интернэтгүй нээгддэг asset gallery, брэндийн тайлбар.
branding/BRAND_GUIDE_MN.txt — лого/маскотыг хаана хэрхэн хэрэглэх дүрэм.
assets/ — сайтад шууд ашиглах тусдаа PNG файлууд.

2. CLAUDE CODE-Д БҮТЭЭЛГЭХ ХАМГИЙН АМАР АРГА
а. Компьютер дээр angaa-auto-work нэртэй ажлын хавтас үүсгэ.
б. ZIP-ээс гарсан ANGAA_AUTO хавтсыг дотор нь хуулж, handoff гэж нэрлэ.
в. Claude Code-ийг angaa-auto-work хавтас дээр нээ.
г. START_CLAUDE.txt файлын START MESSAGE хэсгийг бүтнээр нь өг.
д. Claude handoff-ийн хажууд app/ хавтас үүсгэж Laravel application-ийг хийнэ. handoff/ нь asset ба шаардлага; app/ нь жинхэнэ код.
е. Нэг execution window-д багтахгүй бол START_CLAUDE.txt-ийн CONTINUE MESSAGE-ийг өг; app/PROGRESS.md-ээс үргэлжилнэ.

Эцэст нь: ажлын root дотор handoff/ болон app/ гэсэн хоёр хавтас байна. Laravel командууд app/ дотроос ажиллана. ZIP өөрөө composer/npm project биш тул ZIP root дээр npm run dev гэж ажиллуулахгүй.

3. СИСТЕМД ЗААВАЛ ОРОХ ХЭСГҮҮД
• Монгол хэлтэй брэндийн сайт; зурагтай засварын хүсэлт; буцааж залгах/очиж үйлчлэх хүсэлтийн ялгаа.
• Хэрэглэгчийн баталгаажсан нэвтрэлт; өөрийн машины зурагтай түүх; үнийн санал зөвшөөрөх.
• Filament 5 админ; цагийн календарь; пост/механикийн давхардлыг сервер дээр хаах.
• Хүлээн авалт, км, онош, сэлбэг, засвар, QC, олголт.
• Нэхэмжлэл, гар аргаар бүртгэх төлбөр/үлдэгдэл; хугацаа/км-д тулгуурласан сануулга.
• Механикийн утасны дэлгэц, зөвшөөрөл, private зураг, тайлан, backup ба server runbook.

4. STACK БА LOCAL ГОРИМ
Laravel 13, Filament 5, Livewire 4, Blade, Tailwind 4.1+, Vite.
Local default: SQLite + database queue + private зураг + туршилтын SMS log driver. Бодит үйлчилгээ/credentials шаардахгүйгээр demo-г бүтнээр нь ажиллуулна.
Production target: MySQL 8.4, compatible PHP-FPM, HTTPS, persistent queue worker, scheduler cron, private files, бодит SMS provider.
SQLite → MySQL нь schema/data/photo шилжүүлэлттэй; файлын нэр солих үйлдэл биш. app:readiness шалгалт болон staging туршилт заавал хийнэ.

5. БРЭНДИЙГ КОДОНД ХОЛБОХ
scripts/install-brand-assets.sh болон scripts/install-brand-assets.ps1 нь ЗӨВХӨН зурагнуудыг бэлэн Laravel app/public/brand руу хуулна. Laravel суулгахгүй. Ижил нэртэй өөр файл байвал дарж бичихгүй.
Жишээ Bash: bash handoff/scripts/install-brand-assets.sh app
Жишээ PowerShell: & .\handoff\scripts\install-brand-assets.ps1 -ProjectRoot .\app
Дэлгэрэнгүй: docs/09_BUILD_AND_ASSET_HANDOFF_MN.txt

6. БОДИТ НЭЭЛТИЙН ӨМНӨ АВАХ МЭДЭЭЛЭЛ
Ангаагийн бодит утас, байршил/map pin, цагийн хуваарь, хийдэг үйлчилгээ, үнэ тогтоох бодлого, механик/постын тоо, очих үйлчилгээний хүрээ, SMS provider, үйлчилгээний баталгааны бодлого, нийтлэх бодит зураг.
Эдгээрийг зохиохгүй; local demo дээр DEMO гэж тэмдэглэнэ. «24/7», «№1», албан гэрчилгээ, баталгаат хугацаа, бодит бус сэтгэгдэл нийтлэхгүй.

7. ЗУРГИЙН ТАЙЛБАР
PNG файлууд built-in image generation-оор үүсгэсэн raster asset. Primary logo, mark болон 2 mascot нь alpha transparency-тай. Reversed logo нь navy дэвсгэртэй opaque PNG. Brand board-ийн самбар/хувцас нь дүрслэлийн mockup; Ангаагийн одоогийн байр/ажилтан гэж үзэхгүй.
Их хэмжээний самбар, хатгамалд ашиглахдаа primary logo-г баримталж вектор болгон цэвэр зурж, материал/хэмжээгээр нь хэвлэлийн газар proof гаргана. Хуурамч SVG буюу PNG-г SVG дотор embed хийгээд вектор гэж нэрлэхгүй.
Нэрийг хэрэглэгч сонгосон; домэйн болон нэрийн эрхийн бүртгэл баталгаажаагүй.

8. ХААНА ЮУ БАЙГАА ВЭ
START_CLAUDE.txt — copy/paste эхлэх ба үргэлжлүүлэх message.
docs/01_CLAUDE_CODE_PROMPT.txt — бүрэн master build prompt.
docs/02_BUSINESS_AND_BRAND_MN.txt — бизнесийн байршуулалт.
docs/03_PRODUCT_SPEC.txt — дэлгэц ба workflow.
docs/04_ARCHITECTURE_AND_DATA.txt — database, authorization, calendar, media, reminder дүрэм.
docs/05_ACCEPTANCE_AND_ROLLOUT.txt — үе шат ба бодит acceptance тестүүд.
docs/06_RESEARCH_SOURCES.txt — өмнөх судалгааны эх сурвалж ба хязгаар.
docs/09_BUILD_AND_ASSET_HANDOFF_MN.txt — setup/build/сервер шилжүүлэлтийн тайлбар.
branding/ — brand board, guide, preview, tokens, CSS, asset map, generation prompts.
scripts/ — зөвхөн asset суулгах, багцын бүрэн бүтэн байдлыг шалгах utility.
MANIFEST.json — файлын хэмжээ/хэш ба PNG хэмжээ/alpha шалгалт.
