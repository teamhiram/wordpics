-- 追加の公式画像（016〜018 / 名刺・横向き）
-- phpMyAdmin で zebulun_wordpics を選択 → SQL タブに貼り付け → 実行
--
-- INSERT IGNORE なので、既に登録済みの行はスキップされます。
-- 何度実行しても安全です。

SET NAMES utf8mb4;

INSERT IGNORE INTO submissions
  (id, author_user_id, is_user_submission, status, original_path, mime_type,
   book_abbr, chapter, verse, verse_text, citation_ja, size, orientation, tags,
   created_at, updated_at, approved_at)
VALUES
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
