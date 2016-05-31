<?php

use yii\db\Migration;

class m160531_192922_add_insert_unique_index extends Migration
{
	// Use safeUp/safeDown to run migration code within a transaction
	public function safeDown() {
		$this->execute('DROP INDEX public.queue_delayed_unique_insert_index CASCADE');
		$this->execute('DROP FUNCTION public.crc32(TEXT)');
	}

	public function safeUp() {
		$function = <<<SQL
		CREATE OR REPLACE FUNCTION crc32(text_string TEXT) RETURNS BIGINT AS $$
DECLARE
  tmp BIGINT;
  i INT;
  j INT;
  byte_length INT;
  binary_string BYTEA;
BEGIN
  IF text_string = '' THEN
    RETURN 0;
  END IF;

  i = 0;
  tmp = 4294967295;
  byte_length = bit_length(text_string) / 8;
  binary_string = decode(replace(text_string, E'\\\\\\\\', E'\\\\\\\\\\\\\\\\'), 'escape');
  LOOP
    tmp = (tmp # get_byte(binary_string, i))::BIGINT;
    i = i + 1;
    j = 0;
    LOOP
      tmp = ((tmp >> 1) # (3988292384 * (tmp & 1)))::BIGINT;
      j = j + 1;
      IF j >= 8 THEN
        EXIT;
      END IF;
    END LOOP;
    IF i >= byte_length THEN
      EXIT;
    END IF;
  END LOOP;
  RETURN (tmp # 4294967295);
END
$$ IMMUTABLE LANGUAGE plpgsql
SQL;

		$index = <<<SQL
CREATE UNIQUE INDEX "queue_delayed_unique_insert_index" ON public.queue_delayed (
  "job",
  crc32("data"::TEXT),
  crc32("data_plain"::TEXT),
  "unique"
) WHERE "unique" = TRUE;
SQL;

		$this->execute($function);
		$this->execute($index);
	}


}
