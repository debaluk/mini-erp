const BULAN_INDO = [
  "", "Januari", "Februari", "Maret", "April", "Mei", "Juni",
  "Juli", "Agustus", "September", "Oktober", "November", "Desember"
];

const HARI_INDO = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];

export const DateHelper = {
  /**
   * Output: 04 Oktober 2026
   */
  formatIndo(dateString) {
    if (!dateString) return '-';
    const d = new Date(dateString);
    if (isNaN(d)) return '-';
    const tgl = String(d.getDate()).padStart(2, '0');
    const bln = BULAN_INDO[d.getMonth() + 1];
    const thn = d.getFullYear();
    return `${tgl} ${bln} ${thn}`;
  },

  /**
   * Output: Minggu, 04 Oktober 2026
   */
  formatIndoFull(dateString) {
    if (!dateString) return '-';
    const d = new Date(dateString);
    if (isNaN(d)) return '-';
    const hari = HARI_INDO[d.getDay()];
    return `${hari}, ${this.formatIndo(dateString)}`;
  },

  /**
   * Output: 04/10/2026
   */
  formatShort(dateString) {
    if (!dateString) return '-';
    const d = new Date(dateString);
    if (isNaN(d)) return '-';
    const tgl = String(d.getDate()).padStart(2, '0');
    const bln = String(d.getMonth() + 1).padStart(2, '0');
    return `${tgl}/${bln}/${d.getFullYear()}`;
  },

  /**
   * Output: 20261004 (Untuk Kode Transaksi)
   */
  formatCompact(date = new Date()) {
    const d = new Date(date);
    const thn = d.getFullYear();
    const bln = String(d.getMonth() + 1).padStart(2, '0');
    const tgl = String(d.getDate()).padStart(2, '0');
    return `${thn}${bln}${tgl}`;
  }
};
