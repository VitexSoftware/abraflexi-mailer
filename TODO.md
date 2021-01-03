
1. načíst bankovní doklady, které splňují nějaká kritéria (1 request do flexibee
   api, optimalizovaný, aby mi vracel jen to co potřebuji)

2. for cyklus nad výsledkem jednotky

3. uvnitr for cyklu 1 request do api na faktury, vyfiltrovany rovnou tak, aby se
   vrátili jen doklady, které se mají napárovat. použít order by, detail custom 
   a includes abych nemusel dělat další requesty.

4. další for cyklus nad výsledkem trojky

5. spárování konkrétní faktury a konkrétní banky + zvýšení proměnné, která si v 
   sobě pamatuje celkovou spárovanou částku

6. jakmile je celková spárovaná částka vyšší nebo rovna zbývá spárovat na bance 
   tak break a další banka

Poznámky:
- jaké bankovní doklady se párují ovlivňuje podmínky filtrace v bodě 1. možnosti
  zaškrtávátka, datum od do atd.
- párovací metoda (var. sym. x spec. sym. x cokoliv jiného) ovlivňuje filtraci v
  bodě 3. nemělo by být potřeba nic dalšího, jen ten případný break abych 
  nepřerostl částku banky!!!
- algoritmus musí být takto, protože pokud by k načtení došlo už na začátku, byl 
  by problém uhlídat několikanásobné napárování na jednu fakturu
- pozor také na to, že měny mohou být na bance a faktuře jiné. A srovnávat to 
  podle sumCelkem nemusí být správné pokud páruji EUR a EUR.
- ještě budu potřebovat jeden dotaz, abych si zjistil z nastavení jaká je 
  tuzemská měna

Složitost:
n+2 dotazů kde n je počet bankovních dokladů, které do párování vstoupí... ???...
asi jo... zápisy do flexibee budou proměnnlivé podle toho co se všechno spáruje...


 typ-faktury-vydane **typDoklK**:
    *  Standardní faktura (typDokladu.faktura)
    *  Dobropis/opravný daň. d. (typDokladu.dobropis)
    *  Zálohová faktura (typDokladu.zalohFaktura)
    *  Zálohový daňový doklad (typDokladu.zdd)
    *  Dodací list (typDokladu.dodList)
    *  Proforma (neúčetní) (typDokladu.proforma)
    *  Pohyb Kč / Zůstatek Kč (typBanUctu.kc)
    *  Pohyb měna / Zůstatek měna (typBanUctu.mena)
