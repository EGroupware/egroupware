<?php
// Fixture placeholder for CustomfieldsTest::testGetOptionsFromHeaderIncPhpBlocked() -
// NOT a real header.inc.php, just a file with this exact basename to exercise
// Customfields::get_options_from_file()'s explicit "don't allow to include our header
// again" guard. Content is irrelevant - the guard rejects the file by basename alone,
// before ever reading its contents.
