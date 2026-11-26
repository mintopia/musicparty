<script setup lang="ts">
import SearchSectionHead from '@/components/Search/SearchSectionHead.vue';
import TrackRowPlaceholder from '@/components/TrackRowPlaceholder.vue';
</script>

<template>
    <div class="placeholder-wave" aria-busy="true" aria-label="Loading results" data-test="results-loading">
        <div class="row g-4 mt-0">
            <div class="col-md-5">
                <SearchSectionHead title="Top result" />
                <div class="card" data-test="top-placeholder">
                    <div class="card-body d-flex flex-column gap-3">
                        <span class="placeholder rounded top-cover-placeholder"></span>
                        <div>
                            <div><span class="placeholder col-8 top-title-placeholder"></span></div>
                            <div class="mt-2"><span class="placeholder placeholder-sm col-5"></span></div>
                        </div>
                        <div><span class="placeholder btn btn-primary disabled action-placeholder"></span></div>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <SearchSectionHead title="Tracks" />
                <div class="card">
                    <div class="list-group list-group-flush">
                        <TrackRowPlaceholder v-for="n in 4" :key="n" compact />
                    </div>
                </div>
            </div>
        </div>
        <SearchSectionHead title="Albums" />
        <div class="d-flex gap-3 overflow-hidden" data-test="album-placeholders">
            <div v-for="n in 5" :key="n" class="tile-placeholder album-tile-placeholder">
                <span class="placeholder rounded d-block w-100 tile-art"></span>
                <div class="mt-2"><span class="placeholder col-10"></span></div>
                <div class="mt-1"><span class="placeholder placeholder-sm col-6"></span></div>
            </div>
        </div>
        <SearchSectionHead title="Artists" />
        <div class="d-flex gap-3 overflow-hidden" data-test="artist-placeholders">
            <div v-for="n in 5" :key="n" class="tile-placeholder artist-tile-placeholder">
                <span class="placeholder rounded-circle d-block w-100 tile-art"></span>
                <div class="mt-2"><span class="placeholder col-10"></span></div>
                <div class="mt-1"><span class="placeholder placeholder-sm col-6"></span></div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.top-cover-placeholder {
    display: block;
    width: 88px;
    height: 88px;
}

.top-title-placeholder {
    height: 26px;
}

.action-placeholder {
    width: 112px;
    height: 36px;
}

.tile-placeholder {
    flex: 0 0 auto;
}

.tile-art {
    aspect-ratio: 1;
}

.album-tile-placeholder {
    width: 132px;
}

.artist-tile-placeholder {
    width: 104px;
}

@media (min-width: 768px) {
    .top-cover-placeholder {
        width: 120px;
        height: 120px;
    }

    .album-tile-placeholder {
        width: 150px;
    }

    .artist-tile-placeholder {
        width: 130px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .placeholder-wave .placeholder {
        animation: none;
    }
}
</style>
