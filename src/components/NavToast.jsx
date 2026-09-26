import styles from '../styles/navtoast.module.css';

export default function NavToast({ url, onDismiss }) {
    const pageName = url.split('/').pop().replace('.php', '').replace('-', ' ');

    return (
        <div className={styles.toast}>
            <span className={styles.msg}>
                Ready to open <strong>{pageName}</strong>. Navigation only happens when you approve it.
            </span>
            <div className={styles.actions}>
                <button className={styles.goBtn} onClick={() => { window.location.href = url; }}>
                    Go now
                </button>
                <button className={styles.cancelBtn} onClick={onDismiss}>
                    Cancel
                </button>
            </div>
        </div>
    );
}
