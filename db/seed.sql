-- 既存の公式画像 15 枚を submissions へシード（status='approved', is_user_submission=0）
-- `original_path` は `pics/...` のような相対パスで、サイトから直接配信される

SET NAMES utf8mb4;

INSERT IGNORE INTO submissions
  (id, author_user_id, is_user_submission, status, original_path, mime_type,
   book_abbr, chapter, verse, verse_text, citation_ja, size, orientation, tags,
   created_at, updated_at, approved_at)
VALUES
  ('001', NULL, 0, 'approved', 'pics/square/001-2Co-3-17.png', 'image/png',
   '2Co', 3, '17', '主の霊のあるところには自由がある。', 'IIコリント 3:17',
   'square', 'square', JSON_ARRAY('希望','自由','聖霊','光','空','朝焼け'),
   NOW() - INTERVAL 30 DAY, NOW() - INTERVAL 30 DAY, NOW() - INTERVAL 30 DAY),

  ('002', NULL, 0, 'approved', 'pics/businesscards/002-Psm-34-18.png', 'image/png',
   'Psm', 34, '18', 'エホバは心の砕かれた者たちに近く、霊の中で悔いた者たちを救われる。', '詩篇 34章18節',
   'businesscard', 'landscape', JSON_ARRAY('慰め','悔い改め','夕暮れ','湖','静寂'),
   NOW() - INTERVAL 29 DAY, NOW() - INTERVAL 29 DAY, NOW() - INTERVAL 29 DAY),

  ('003', NULL, 0, 'approved', 'pics/businesscards/003-Psm-119-105.png', 'image/png',
   'Psm', 119, '105', 'あなたの言葉はわが足のともし火、わが径の光です。', '詩篇 119章105節',
   'businesscard', 'landscape', JSON_ARRAY('導き','み言葉','光','夜','ランタン','道'),
   NOW() - INTERVAL 28 DAY, NOW() - INTERVAL 28 DAY, NOW() - INTERVAL 28 DAY),

  ('004', NULL, 0, 'approved', 'pics/postcards/004-Phl-4-6.png', 'image/png',
   'Phl', 4, '6', '何事にも思い煩うことなく、あらゆることにおいて、感謝をささげることを伴う祈りと願い求めによって、あなたがたの要望を神に知らせなさい。', 'ピリピ 4:6',
   'postcard', 'portrait', JSON_ARRAY('祈り','感謝','平安','湖','花'),
   NOW() - INTERVAL 27 DAY, NOW() - INTERVAL 27 DAY, NOW() - INTERVAL 27 DAY),

  ('005', NULL, 0, 'approved', 'pics/postcards/005-Rom-12-2.png', 'image/png',
   'Rom', 12, '2', 'またこの時代にかたどられてはいけません。むしろ、思いが新しくされることによって造り変えられなさい。それは、何が神の御心であるか、すなわち何が善であって、喜ばれ、完全なものであるかを、あなたがたがわきまえるようになるためです。', 'ローマ人への手紙 12章2節',
   'postcard', 'portrait', JSON_ARRAY('希望','新生','変化','御心','花畑','青空','春'),
   NOW() - INTERVAL 26 DAY, NOW() - INTERVAL 26 DAY, NOW() - INTERVAL 26 DAY),

  ('006', NULL, 0, 'approved', 'pics/postcards/006-Eph-1-9.png', 'image/png',
   'Eph', 1, '9', 'みこころの奥義をわたしたちに知らせてくださいました。これは、神がご自身の中で計画された彼の大いなる喜びによるもので、', 'エペソ人への手紙 1章9節',
   'postcard', 'landscape', JSON_ARRAY('喜び','奥義','計画','光','朝日','野花','英文'),
   NOW() - INTERVAL 25 DAY, NOW() - INTERVAL 25 DAY, NOW() - INTERVAL 25 DAY),

  ('007', NULL, 0, 'approved', 'pics/postcards/007-Psm-107-29.png', 'image/png',
   'Psm', 107, '29', '彼が嵐を静められると、その波は穏やかになった。', '詩篇 107章29節',
   'postcard', 'landscape', JSON_ARRAY('平安','嵐','海','平穏','光芒'),
   NOW() - INTERVAL 24 DAY, NOW() - INTERVAL 24 DAY, NOW() - INTERVAL 24 DAY),

  ('008', NULL, 0, 'approved', 'pics/postcards/008-Psm-107-35.png', 'image/png',
   'Psm', 107, '35', '彼は荒野を水のある池に、乾いた地を水の泉に変えられる。', '詩篇 107章35節',
   'postcard', 'landscape', JSON_ARRAY('希望','変化','荒野','命','泉','自然','夏'),
   NOW() - INTERVAL 23 DAY, NOW() - INTERVAL 23 DAY, NOW() - INTERVAL 23 DAY),

  ('009', NULL, 0, 'approved', 'pics/postcards/009-Psm-36-9.png', 'image/png',
   'Psm', 36, '9', 'あなたと共に、命の源泉があり、あなたの光の中で、わたしたちは光を見るのです。', '詩篇 36章9節',
   'postcard', 'landscape', JSON_ARRAY('希望','命','光','源泉','滝','緑','夏'),
   NOW() - INTERVAL 22 DAY, NOW() - INTERVAL 22 DAY, NOW() - INTERVAL 22 DAY),

  ('010', NULL, 0, 'approved', 'pics/businesscards/010-Pro-3-6.png', 'image/png',
   'Pro', 3, '6', 'あなたのすべての道で彼を認めよ、そうすれば、彼はあなたの路を真っすぐにされる。', '箴言 3章6節',
   'businesscard', 'portrait', JSON_ARRAY('導き','御心','道','光','緑','自然','青空','夏'),
   NOW() - INTERVAL 21 DAY, NOW() - INTERVAL 21 DAY, NOW() - INTERVAL 21 DAY),

  ('011', NULL, 0, 'approved', 'pics/postcards/011-1Co-10-13.png', 'image/png',
   '1Co', 10, '13', 'あなたがたに臨んだ試みで、人の常でないものはありません。神は信実であって、あなたがたが耐えられないような試みに遭うことを許されません。むしろ、あなたがたがそれに耐えることができるようにと、その試みと共に、出て行く道をも備えてくださいます。', 'コリント人への第一の手紙 10章13節',
   'postcard', 'landscape', JSON_ARRAY('励まし','試練','信実','逃れの道','夕日','海辺','草原'),
   NOW() - INTERVAL 20 DAY, NOW() - INTERVAL 20 DAY, NOW() - INTERVAL 20 DAY),

  ('012', NULL, 0, 'approved', 'pics/postcards/012-Mat-6-28.png', 'image/png',
   'Mat', 6, '28', 'また、なぜあなたがたは、衣服について思い煩うのか？ 野のゆりがどのように生長するか、よく考えてみなさい。それらは労苦もせず、紡ぎもしない。', 'マタイによる福音書 6章28節',
   'postcard', 'landscape', JSON_ARRAY('平安','花','花畑','野花','空','光','自然','夏'),
   NOW() - INTERVAL 19 DAY, NOW() - INTERVAL 19 DAY, NOW() - INTERVAL 19 DAY),

  ('013', NULL, 0, 'approved', 'pics/postcards/013-Isa-1-18.png', 'image/png',
   'Isa', 1, '18', 'たとえあなたがたの罪が緋のようであっても、雪のように白くなる。たとえ紅のように赤くても、羊の毛のようになる。', 'イザヤ書 1章18節',
   'postcard', 'landscape', JSON_ARRAY('希望','慰め','新生','冬','光','自然','雪','羊'),
   NOW() - INTERVAL 18 DAY, NOW() - INTERVAL 18 DAY, NOW() - INTERVAL 18 DAY),

  ('014', NULL, 0, 'approved', 'pics/postcards/014-Jer-17-8.png', 'image/png',
   'Jer', 17, '8', '彼は水のほとりに移植された木のようになり、その根を川のそばに伸ばし、暑さが来ても恐れない。その葉は茂ったままで、干ばつの年にも心配することはなく、実を結ぶことをやめない。', 'エレミヤ書 17章8節',
   'postcard', 'landscape', JSON_ARRAY('希望','平安','命','夏','光','青空','緑','自然','木','川'),
   NOW() - INTERVAL 17 DAY, NOW() - INTERVAL 17 DAY, NOW() - INTERVAL 17 DAY),

  ('015', NULL, 0, 'approved', 'pics/postcards/015-Psm-126-5.png', 'image/png',
   'Psm', 126, '5', '涙をもって種をまく者たちは、喜びの響きわたる叫びをもって刈り取る。', '詩篇 126章5節',
   'postcard', 'landscape', JSON_ARRAY('希望','喜び','慰め','励まし','秋','夕日','空','自然','草原'),
   NOW() - INTERVAL 16 DAY, NOW() - INTERVAL 16 DAY, NOW() - INTERVAL 16 DAY),

  ('016', NULL, 0, 'approved', 'pics/businesscards/016-Jhn-8-12.png', 'image/png',
   'Jhn', 8, '12', 'わたしは世の光である。わたしに従う者は、決して暗やみの中を歩くことがなく、命の光を持つ。',
   'ヨハネによる福音書 8:12（後半）',
   'businesscard', 'landscape', JSON_ARRAY('希望','導き','光','命','み言葉','朝日','草原','道','自然'),
   NOW() - INTERVAL 15 DAY, NOW() - INTERVAL 15 DAY, NOW() - INTERVAL 15 DAY),

  ('017', NULL, 0, 'approved', 'pics/businesscards/017-Mat-10-31.png', 'image/png',
   'Mat', 10, '31', 'だから、恐れてはならない．あなたがたは、多くのすずめよりもはるかに貴重である。',
   'マタイによる福音書 10:31',
   'businesscard', 'landscape', JSON_ARRAY('励まし','慰め','平安','信頼','春','花','自然'),
   NOW() - INTERVAL 14 DAY, NOW() - INTERVAL 14 DAY, NOW() - INTERVAL 14 DAY),

  ('018', NULL, 0, 'approved', 'pics/businesscards/018-Mat-10-16.png', 'image/png',
   'Mat', 10, '16', 'はとのように純真でありなさい。',
   'マタイによる福音書 10:16',
   'businesscard', 'landscape', JSON_ARRAY('純真','静寂','平安','自然','光','花'),
   NOW() - INTERVAL 13 DAY, NOW() - INTERVAL 13 DAY, NOW() - INTERVAL 13 DAY);
