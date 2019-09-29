import vcr

my_vcr = vcr.VCR(
    serializer='json',
    cassette_library_dir='fixtures/cassettes',
    record_mode='none',
    match_on=['uri', 'method'],
)
