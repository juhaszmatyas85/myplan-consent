import re
pot=open('myplan-consent.pot',encoding='utf-8').read()
def unq(lines): return ''.join(re.findall(r'"((?:[^"\\]|\\.)*)"',lines)).replace('\\"','"').replace('\\\\','\\')
def q(s): return '"'+s.replace('\\','\\\\').replace('"','\\"').replace('\n','\\n')+'"'

F='formal'; I='informal'
T={
('', 'MyPlan Cookie Consent'):'MyPlan Cookie Consent',
('', 'MyPlan'):'MyPlan',
('', 'Cookie consent'):'Sütikezelés',
('', 'Settings'):'Beállítások',
('', 'General'):'Általános',
('', 'Show the banner and apply consent'):'A sütisáv megjelenítése és a hozzájárulás érvényesítése',
('', 'Google Consent Mode'):'Google Consent Mode',
('', 'Advanced: Google tags load at once with consent denied (cookieless pings, modelled conversions)'):'Advanced: a Google-címkék azonnal betöltődnek, megtagadott hozzájárulással (süti nélküli pingek, modellezett konverziók)',
('', 'Basic: Google tags load only after consent'):'Basic: a Google-címkék csak hozzájárulás után töltődnek be',
('', 'Google Tag Manager ID'):'Google Tag Manager azonosító',
('', 'GTM-XXXXXXX. Leave empty if another plugin or the theme loads it; the consent defaults still come first.'):'GTM-XXXXXXX. Hagyd üresen, ha más plugin vagy a téma tölti be; az alapértelmezett hozzájárulási állapot akkor is elsőként kerül ki.',
('', 'Google Analytics 4 ID'):'Google Analytics 4 azonosító',
('', 'G-XXXXXXXXXX. Only if GA4 is not loaded through Tag Manager.'):'G-XXXXXXXXXX. Csak ha a GA4 nem a Tag Manageren keresztül töltődik be.',
('', 'Redact ad click identifiers while ad consent is denied (ads_data_redaction)'):'Hirdetési kattintásazonosítók kitakarása, amíg a hirdetési hozzájárulás nincs megadva (ads_data_redaction)',
('', 'Pass ad click information through URLs while consent is denied (url_passthrough)'):'Hirdetési kattintásadatok továbbítása URL-ben, amíg nincs hozzájárulás (url_passthrough)',
('', 'wait_for_update (ms)'):'wait_for_update (ms)',
('', 'How long Google tags wait for a stored choice before firing.'):'Ennyit várnak a Google-címkék a tárolt döntésre, mielőtt lefutnak.',
('', 'Categories and cookies'):'Kategóriák és sütik',
('', 'Functional (embedded maps, videos, preferences)'):'Funkcionális (beágyazott térképek, videók, beállítások)',
('', 'Analytics'):'Statisztikai',
('', 'Marketing'):'Marketing',
('', 'Strictly necessary cookies'):'Feltétlenül szükséges sütik',
('', 'One per line: name | provider | purpose | expiry. The consent cookie itself is always listed.'):'Soronként egy: név | szolgáltató | cél | lejárat. A hozzájárulást tároló süti mindig szerepel a listában.',
('', 'Functional cookies'):'Funkcionális sütik',
('', 'Analytics cookies'):'Statisztikai sütik',
('', 'Marketing cookies'):'Marketing sütik',
('', 'Listed cookies are also deleted when a visitor withdraws the category (a trailing * matches a prefix).'):'A felsorolt sütiket a plugin törli is, ha a látogató visszavonja a kategóriát (a végén * előtag-egyezést jelent).',
('', 'Blocking'):'Blokkolás',
('', 'Script handles'):'Szkript-azonosítók (handle)',
('', 'One per line: handle = category. These enqueued scripts run only after consent. In your own markup use <script type="text/plain" data-mpc-consent="analytics">.'):'Soronként egy: handle = kategória. Ezek a betöltött szkriptek csak hozzájárulás után futnak. Saját kódban: <script type="text/plain" data-mpc-consent="analytics">.',
('', 'Embed rules'):'Beágyazási szabályok',
('', 'One per line: address fragment = category | provider. Matching iframes in content get a placeholder until consent. Themes can use mpc_embed( $iframe ).'):'Soronként egy: címrészlet = kategória | szolgáltató. Az illeszkedő iframe-ek helyén hozzájárulásig helyőrző jelenik meg. Témákban: mpc_embed( $iframe ).',
('', 'Appearance and texts'):'Megjelenés és szövegek',
('', 'Form of address'):'Megszólítás',
('', 'Formal'):'Magázó',
('', 'Informal'):'Tegező',
('', 'Banner position'):'A sáv helye',
('', 'Bar along the bottom'):'Sáv az oldal alján',
('', 'Box in the bottom left corner'):'Doboz a bal alsó sarokban',
('', 'Show a floating button to reopen the settings'):'Lebegő gomb a beállítások újbóli megnyitásához',
('', 'Without it, put [mpc_settings_link] or a link to #cookie-settings in the footer: withdrawing consent must be as easy as giving it.'):'Nélküle tegyél a láblécbe [mpc_settings_link] rövidkódot vagy #cookie-settings hivatkozást: a hozzájárulás visszavonása legyen olyan egyszerű, mint a megadása.',
('', 'Accent colour'):'Kiemelőszín',
('', 'Background colour'):'Háttérszín',
('', 'Text colour'):'Szövegszín',
('', 'Corner radius (px)'):'Saroklekerekítés (px)',
('', 'Banner title'):'A sáv címe',
('', 'Leave the texts empty to use the built-in, translated wording.'):'Üresen hagyva a beépített, lefordított szöveg jelenik meg.',
('', 'Banner text'):'A sáv szövege',
('', 'Settings dialog introduction'):'A beállítások ablak bevezetője',
('', 'Cookie policy page'):'Cookie-szabályzat oldal',
('', 'Privacy notice page'):'Adatkezelési tájékoztató oldal',
('', 'Consent validity and log'):'A hozzájárulás érvényessége és naplója',
('', 'Ask again after (days)'):'Újrakérdezés ennyi nap után',
('', 'Applies to refusals too. Recommended: 180 (6 months): the EU Digital Omnibus proposal bars asking again within six months of a refusal. At most 395 (13 months).'):'Az elutasításra is vonatkozik. Javasolt: 180 (6 hónap): az uniós Digital Omnibus javaslat szerint elutasítás után hat hónapon belül nem szabad újra kérdezni. Legfeljebb 395 (13 hónap).',
('', 'Ask every visitor again on save'):'Mentéskor minden látogató újra döntsön',
('', 'Use when the categories or the purposes change.'):'Akkor használd, ha a kategóriák vagy a célok megváltoznak.',
('', 'Treat the browser’s Global Privacy Control signal as “reject all”'):'A böngésző Global Privacy Control jelzése „mindet elutasítom”-nak számít',
('', 'The EU Digital Omnibus proposal would make browser-level signals binding; honouring them now is safe, since refusal is the default anyway.'):'Az uniós Digital Omnibus javaslat kötelezővé tenné a böngészőszintű jelzések figyelembevételét; már most biztonságos, hiszen az elutasítás amúgy is az alapállapot.',
('', 'Keep a consent log (no IP address is stored)'):'Hozzájárulási napló vezetése (IP-cím nélkül)',
('', 'Keep log entries for (days)'):'Naplóbejegyzések megőrzése (nap)',
('', 'On'):'Be',
('', '— None —'):'— Nincs —',
('', 'Decisions in the last 30 days'):'Döntések az elmúlt 30 napban',
('', 'Accepted all: %1$d · Rejected all: %2$d · Custom choice: %3$d · Allowed from an embed: %4$d · Browser refusal (GPC): %5$d'):'Mindet elfogadta: %1$d · Mindet elutasította: %2$d · Egyéni választás: %3$d · Beágyazásból engedélyezte: %4$d · Böngésző-elutasítás (GPC): %5$d',
('', 'Download the consent log (CSV)'):'Hozzájárulási napló letöltése (CSV)',
('', 'Current policy version: %d'):'Jelenlegi szabályzatverzió: %d',
('', 'You are not allowed to do this.'):'Ehhez nincs jogosultságod.',
('', 'Distinguishes visitors'):'Megkülönbözteti a látogatókat',
('', '2 years'):'2 év',
('', 'Keeps the session state'):'A munkamenet állapotát tárolja',
('', 'Measures ad conversions'):'A hirdetési konverziókat méri',
('', '90 days'):'90 nap',
('', 'Measures ad performance'):'A hirdetések teljesítményét méri',
('', 'Cookies on this website'):'Sütik a weboldalon',
(I, 'We use cookies that are necessary for this website to work. With your consent, we also use optional cookies and third-party services. You can change your decision at any time.'):'A weboldal működéséhez szükséges sütiket használunk. A hozzájárulásoddal további sütiket és külső szolgáltatásokat is használunk. A döntésedet bármikor módosíthatod.',
(F, 'We use cookies that are necessary for this website to work. With your consent, we also use optional cookies and third-party services. You can change your decision at any time.'):'A weboldal működéséhez szükséges sütiket használunk. Az Ön hozzájárulásával további sütiket és külső szolgáltatásokat is használunk. Döntését bármikor módosíthatja.',
('', 'Cookie settings'):'Sütibeállítások',
(I, 'Choose which categories of cookies you allow. Strictly necessary cookies are always active, because the website cannot work without them. You can change your decision at any time with the cookie settings button or link.'):'Válaszd ki, mely sütikategóriákat engedélyezed. A feltétlenül szükséges sütik mindig aktívak, mert nélkülük a weboldal nem működik. A döntésedet bármikor módosíthatod a Sütibeállítások gombbal vagy hivatkozással.',
(F, 'Choose which categories of cookies you allow. Strictly necessary cookies are always active, because the website cannot work without them. You can change your decision at any time with the cookie settings button or link.'):'Válassza ki, mely sütikategóriákat engedélyezi. A feltétlenül szükséges sütik mindig aktívak, mert nélkülük a weboldal nem működik. Döntését bármikor módosíthatja a Sütibeállítások gombbal vagy hivatkozással.',
('', 'Accept all'):'Mindet elfogadom',
('', 'Reject all'):'Mindet elutasítom',
('', 'Save my choices'):'Kiválasztottak mentése',
('', 'Close'):'Bezárás',
('', 'Always active'):'Mindig aktív',
('', 'Cookies and services'):'Sütik és szolgáltatások',
(I, 'Used only if you allow this category.'):'Csak akkor használjuk őket, ha engedélyezed ezt a kategóriát.',
(F, 'Used only if you allow this category.'):'Csak akkor használjuk őket, ha engedélyezi ezt a kategóriát.',
('', 'Name'):'Név',
('', 'Provider'):'Szolgáltató',
('', 'Purpose'):'Cél',
('', 'Expiry'):'Lejárat',
('', 'Cookie policy'):'Cookie-szabályzat',
('', 'Privacy notice'):'Adatkezelési tájékoztató',
('', 'Allow and show'):'Engedélyezem és megjelenítem',
(I, 'Stores your cookie choices'):'A sütikkel kapcsolatos döntésedet tárolja',
(F, 'Stores your cookie choices'):'A sütikkel kapcsolatos döntését tárolja',
('', 'Strictly necessary'):'Feltétlenül szükséges',
(I, 'Essential for the website to work, for example to remember your cookie choices or keep you signed in. They cannot be switched off.'):'A weboldal működéséhez elengedhetetlenek: például ezek jegyzik meg a sütikkel kapcsolatos döntésedet, vagy tartják fenn a bejelentkezést. Nem kapcsolhatók ki.',
(F, 'Essential for the website to work, for example to remember your cookie choices or keep you signed in. They cannot be switched off.'):'A weboldal működéséhez elengedhetetlenek: például ezek jegyzik meg a sütikkel kapcsolatos döntését, vagy tartják fenn a bejelentkezést. Nem kapcsolhatók ki.',
('', 'Functional'):'Funkcionális',
(I, 'Enable extra features and embedded content from other providers, such as maps and videos, and remember your preferences.'):'Kiegészítő funkciókat és más szolgáltatóktól származó beágyazott tartalmakat (például térképet, videót) tesznek lehetővé, és megjegyzik a beállításaidat.',
(F, 'Enable extra features and embedded content from other providers, such as maps and videos, and remember your preferences.'):'Kiegészítő funkciókat és más szolgáltatóktól származó beágyazott tartalmakat (például térképet, videót) tesznek lehetővé, és megjegyzik a beállításait.',
('', 'Help us understand how visitors use the website, so we can improve it. The information is collected in aggregate.'):'Segítenek megérteni, hogyan használják a látogatók a weboldalt, hogy fejleszthessük. Az adatokat összesítve gyűjtjük.',
(I, 'Used to measure the effectiveness of our advertising and to show you relevant ads on other websites.'):'A hirdetéseink hatékonyságát mérik, és lehetővé teszik, hogy más weboldalakon is számodra releváns hirdetéseket jelenítsünk meg.',
(F, 'Used to measure the effectiveness of our advertising and to show you relevant ads on other websites.'):'A hirdetéseink hatékonyságát mérik, és lehetővé teszik, hogy más weboldalakon is az Ön számára releváns hirdetéseket jelenítsünk meg.',
(I, 'This content is provided by %1$s and may use cookies. To view it, allow the “%2$s” cookies.'):'Ezt a tartalmat a(z) %1$s szolgáltatja, és sütiket használhat. A megtekintéséhez engedélyezd a(z) „%2$s” sütiket.',
(F, 'This content is provided by %1$s and may use cookies. To view it, allow the “%2$s” cookies.'):'Ezt a tartalmat a(z) %1$s szolgáltatja, és sütiket használhat. A megtekintéséhez engedélyezze a(z) „%2$s” sütiket.',
}
PL={'%d day':['%d nap','%d nap']}
head,*blocks=pot.split('\n\n')
out=['''msgid ""
msgstr ""
"Project-Id-Version: MyPlan Cookie Consent 1.0.0\\n"
"Language: hu_HU\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\\n"
"X-Domain: myplan-consent\\n"''']
missing=[]
for b in blocks:
    if 'msgid' not in b: continue
    lines=b.split('\n')
    comments=[l for l in lines if l.startswith('#')]
    body='\n'.join(l for l in lines if not l.startswith('#'))
    ctx=re.search(r'^msgctxt (.*?)(?=^msgid )',body,re.M|re.S)
    ctx=unq(ctx.group(1)) if ctx else ''
    mid=unq(re.search(r'^msgid (.*?)(?=^msgid_plural |^msgstr)',body,re.M|re.S).group(1))
    plural=re.search(r'^msgid_plural (.*?)(?=^msgstr)',body,re.M|re.S)
    e=comments[:]
    if ctx: e.append('msgctxt '+q(ctx))
    e.append('msgid '+q(mid))
    if plural:
        e.append('msgid_plural '+plural.group(1).strip())
        tr=PL.get(mid)
        if not tr: missing.append(mid)
        e+= ['msgstr[0] '+q(tr[0] if tr else ''),'msgstr[1] '+q(tr[1] if tr else '')]
    else:
        tr=T.get((ctx,mid))
        if tr is None:
            # The plugin description has no translation on purpose? translate it.
            missing.append((ctx,mid[:80]))
        e.append('msgstr '+q(tr or ''))
    out.append('\n'.join(e))
open('myplan-consent-hu_HU.po','w',encoding='utf-8').write('\n\n'.join(out)+'\n')
print('missing:',missing)
