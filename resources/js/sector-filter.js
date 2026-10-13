export default function sectorFilter(sectors, initialId = '') {
    const options = sectors.map(sector => ({ ...sector, id: String(sector.id) }));
    const initial = options.find(sector => sector.id === String(initialId));

    return {
        selectedId: initial?.id ?? '',
        query: initial?.name ?? '',
        searchTerm: '',
        open: false,
        searching: false,
        activeIndex: -1,
        get matches() {
            const term = this.searchTerm.trim().toLocaleLowerCase('id');
            return options.filter(sector => sector.name.toLocaleLowerCase('id').includes(term));
        },
        edit(value) {
            this.query = value;
            this.selectedId = '';
            this.searching = true;
            this.activeIndex = -1;
            this.open = true;
        },
        search(value) {
            if (this.selectedId || value !== this.query) return;
            this.searchTerm = value;
            this.searching = false;
        },
        focus() {
            this.searchTerm = this.selectedId ? '' : this.query;
            this.open = true;
        },
        choose(sector) {
            this.selectedId = sector.id;
            this.query = sector.name;
            this.searching = false;
            this.activeIndex = -1;
            this.open = false;
        },
        clear() {
            this.selectedId = '';
            this.query = '';
            this.searchTerm = '';
            this.searching = false;
            this.activeIndex = -1;
            this.open = false;
        },
        move(direction) {
            this.open = true;
            if (this.searching || this.matches.length === 0) return;
            this.activeIndex = this.activeIndex < 0
                ? (direction > 0 ? 0 : this.matches.length - 1)
                : (this.activeIndex + direction + this.matches.length) % this.matches.length;
            this.$nextTick(() => document.getElementById(`sector-filter-option-${this.activeIndex}`)?.scrollIntoView({ block: 'nearest' }));
        },
        chooseActive() {
            if (this.searching) return;
            const sector = this.matches[Math.max(0, this.activeIndex)];
            if (sector) this.choose(sector);
        },
    };
}
