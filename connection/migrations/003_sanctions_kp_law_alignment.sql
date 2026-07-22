-- Migration: Align Sanctions Book with Katarungang Pambarangay Law (RA 7160)
-- Adds a legal reference layer to every sanction entry so barangay officers
-- (and, via the "View Details" modal, the community) can see exactly which
-- law or ordinance authorizes a given penalty, and read a plain explanation.

USE `voice2_db`;

ALTER TABLE `sanctions_book`
  ADD COLUMN `legal_category` enum('kp_law','local_ordinance','barangay_program') NOT NULL DEFAULT 'local_ordinance' AFTER `violation_level`,
  ADD COLUMN `legal_basis` varchar(150) DEFAULT NULL AFTER `ordinance_ref`,
  ADD COLUMN `legal_explanation` text DEFAULT NULL AFTER `legal_basis`;

-- ── Populate accurate legal references for the seeded catalog (barangay_id = 1) ──

-- Attendance (Katarungang Pambarangay Law proper — non-appearance consequences)
UPDATE sanctions_book SET legal_category='kp_law',
  legal_basis='Sec. 412 & 415, R.A. 7160',
  legal_explanation='Personal appearance of parties at Lupon/Pangkat proceedings is mandatory (Sec. 415). A first unexcused absence does not yet penalize the party — the hearing is simply reset and a written warning is issued that a repeat absence carries consequences under Sec. 412 (conciliation as a precondition to filing in court).'
WHERE id=1 AND barangay_id=1;

UPDATE sanctions_book SET legal_category='kp_law',
  legal_basis='Sec. 412(a) & 415, R.A. 7160; Sec. 418 (analogous)',
  legal_explanation='Where the COMPLAINANT fails to appear a second time without justifiable reason, the Lupon Chairman/Pangkat may dismiss the complaint outright. Under Sec. 412(a), prior barangay conciliation is a precondition to filing the same cause of action in court, so a complainant dismissed for repeated non-appearance is barred from refiling that specific complaint judicially.'
WHERE id=2 AND barangay_id=1;

UPDATE sanctions_book SET legal_category='kp_law',
  legal_basis='Sec. 412(b)(4), R.A. 7160',
  legal_explanation='Where the RESPONDENT fails to appear a second time without justifiable reason, Sec. 412(b)(4) allows the complainant to go directly to court despite the general requirement of prior barangay conciliation — the Lupon issues a Certification to File Action (CFA) reflecting the respondent''s repeated non-appearance.'
WHERE id=3 AND barangay_id=1;

UPDATE sanctions_book SET legal_category='kp_law',
  legal_basis='Sec. 410(c) & 413, R.A. 7160',
  legal_explanation='Parties are required to participate in mediation/conciliation in good faith before the Punong Barangay (Sec. 410) or the Pangkat ng Tagapagkasundo (Sec. 413). Outright refusal to engage, though physically present, may be treated by the Lupon as obstruction of the process and referred for appropriate barangay sanction under the Sangguniang Barangay''s ordinance-making power (Sec. 391).'
WHERE id=4 AND barangay_id=1;

UPDATE sanctions_book SET legal_category='local_ordinance',
  legal_explanation='Disrespect toward the Punong Barangay, Lupon members, or barangay staff while performing their Katarungang Pambarangay functions is penalized under this barangay''s own ordinance, adopted pursuant to the Sangguniang Barangay''s general ordinance-making authority (Sec. 391, R.A. 7160). This is a local penal ordinance, not a KP Law provision itself.'
WHERE id=5 AND barangay_id=1;

-- Settlement (KP Law — amicable settlement effect & repudiation)
UPDATE sanctions_book SET legal_category='kp_law',
  legal_basis='Sec. 416, R.A. 7160',
  legal_explanation='An amicable settlement has the force and effect of a final judgment of a court upon the expiration of 10 days from the date of settlement, unless repudiated in that period (Sec. 418). Failing to comply with a final settlement''s agreed terms is treated the same as defying a court judgment and may be enforced by execution through the Lupon (within 6 months) or by ordinary court action thereafter (Sec. 417).'
WHERE id=6 AND barangay_id=1;

UPDATE sanctions_book SET legal_category='kp_law',
  legal_basis='Sec. 416 & 417, R.A. 7160',
  legal_explanation='A party who is late in complying with an already-final settlement is still bound by its terms under Sec. 416. This lesser sanction covers minor delays that fall short of outright non-compliance, before the matter escalates to formal execution proceedings under Sec. 417.'
WHERE id=7 AND barangay_id=1;

-- Public Disturbance / Environment / Curfew / Property (local ordinances)
UPDATE sanctions_book SET legal_category='local_ordinance', legal_explanation='Barangay noise ordinance penalty. Adopted under the Sangguniang Barangay''s ordinance-making power (Sec. 391, R.A. 7160). This is a local penal ordinance, separate from Katarungang Pambarangay conciliation — it may still be raised as a blotter matter subject to barangay mediation before any court referral.' WHERE id=8 AND barangay_id=1;
UPDATE sanctions_book SET legal_category='local_ordinance', legal_explanation='Repeat-offense noise ordinance penalty (escalated fine/community service for a second violation). Adopted under Sec. 391, R.A. 7160.' WHERE id=9 AND barangay_id=1;
UPDATE sanctions_book SET legal_category='local_ordinance', legal_explanation='Public drunkenness / disorderly conduct penalty under local ordinance, adopted under Sec. 391, R.A. 7160. Where the conduct also involves a criminal offense (e.g. alarms and scandals under the Revised Penal Code), the barangay still refers the matter for prosecutorial action after documentation.' WHERE id=10 AND barangay_id=1;
UPDATE sanctions_book SET legal_category='local_ordinance', legal_explanation='Improper waste disposal penalty under the barangay''s environmental/sanitation ordinance, adopted under Sec. 391, R.A. 7160, and consistent with the Ecological Solid Waste Management Act (R.A. 9003).' WHERE id=11 AND barangay_id=1;
UPDATE sanctions_book SET legal_category='local_ordinance', legal_explanation='Illegal dumping penalty under the barangay''s environmental ordinance (Sec. 391, R.A. 7160), read together with R.A. 9003 (Ecological Solid Waste Management Act).' WHERE id=12 AND barangay_id=1;
UPDATE sanctions_book SET legal_category='local_ordinance', legal_explanation='Minor curfew ordinance violation. Adopted under Sec. 391, R.A. 7160. Where the person involved is a minor, the barangay coordinates with the local Council for the Protection of Children rather than imposing a fine directly on the child.' WHERE id=13 AND barangay_id=1;
UPDATE sanctions_book SET legal_category='local_ordinance', legal_explanation='Repeat curfew ordinance violation, escalated penalty. Adopted under Sec. 391, R.A. 7160.' WHERE id=14 AND barangay_id=1;

-- Property (KP Law — disputes properly subject to Lupon conciliation under Sec. 408)
UPDATE sanctions_book SET legal_category='kp_law',
  legal_basis='Sec. 408 & 410, R.A. 7160',
  legal_explanation='Property disputes between residents of the same barangay/city or municipality fall squarely within the Lupon''s conciliation jurisdiction (Sec. 408). Where the parties reach an amicable settlement covering minor property damage, non-compliance is treated under Sec. 416/417 the same as any other settlement.'
WHERE id=15 AND barangay_id=1;

UPDATE sanctions_book SET legal_category='kp_law',
  legal_basis='Sec. 408 & 416, R.A. 7160',
  legal_explanation='Boundary/fence disputes are a classic category of dispute required to first pass through Lupon conciliation (Sec. 408) before either party may go to court. This entry covers violation of a boundary settlement already reached under Sec. 416.'
WHERE id=16 AND barangay_id=1;

-- Procedural (KP Law — jurisdictional/procedural requirements)
UPDATE sanctions_book SET legal_category='kp_law',
  legal_basis='Sec. 410 & 411, R.A. 7160',
  legal_explanation='The Punong Barangay may issue summons and directives during mediation (Sec. 410). Ignoring a lawful barangay order issued in the course of a pending case is treated as non-cooperation with the Katarungang Pambarangay process.'
WHERE id=17 AND barangay_id=1;

UPDATE sanctions_book SET legal_category='kp_law',
  legal_basis='Sec. 412(a), R.A. 7160',
  legal_explanation='Sec. 412(a) makes prior barangay conciliation a precondition to filing most disputes in court. A complaint filed in court without the required Certification to File Action (CFA) or barangay clearance is subject to dismissal by the court itself; this entry documents that procedural lapse at the barangay level.'
WHERE id=18 AND barangay_id=1;

-- Community programs (not law-based — barangay-initiated corrective programs)
UPDATE sanctions_book SET legal_category='barangay_program',
  legal_explanation='Voluntary or agreed community service under a barangay program, typically imposed as an alternative or supplement to a fine within an amicable settlement. Not itself a statutory penalty — its enforceability, if part of a settlement, still derives from Sec. 416, R.A. 7160.'
WHERE id=19 AND barangay_id=1;

UPDATE sanctions_book SET legal_category='barangay_program',
  legal_explanation='Voluntary or agreed community service under a barangay program, typically imposed as an alternative or supplement to a fine within an amicable settlement. Not itself a statutory penalty — its enforceability, if part of a settlement, still derives from Sec. 416, R.A. 7160.'
WHERE id=20 AND barangay_id=1;
