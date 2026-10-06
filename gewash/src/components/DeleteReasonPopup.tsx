import React, { useEffect } from 'react'

interface DeleteReasonPopupProps {
  visible: boolean
  reason: string
  setReason: (value: string) => void
  onConfirm: () => void
  onCancel: () => void
}

export default function DeleteReasonPopup({
  visible,
  reason,
  setReason,
  onConfirm,
  onCancel,
}: DeleteReasonPopupProps) {
  useEffect(() => {
    if (visible) {
      document.body.style.overflow = 'hidden'
    } else {
      document.body.style.overflow = ''
    }

    return () => {
      document.body.style.overflow = ''
    }
  }, [visible])
  if (!visible) return null

  const chars = reason.length
  const isValid = chars >= 24

  return (
    <div className='delete-popup-overlay'>
      <div className='delete-popup'>
        <h3>Deleting a order</h3>
        <p className='leave-a-description'>
          To delete a order, please leave a description of the reason
        </p>
        <div className='textarea-wrapper'>
          <textarea
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            maxLength={500}
            placeholder='Input text'
            className={isValid ? "active" : ""}
          />
          <span className='char-counter'>{chars}/500</span>
        </div>
        <div className='popup-actions'>
          <button onClick={onCancel}>Leave</button>
          <button
            disabled={!isValid}
            className={`confirm-btn ${!isValid ? "disabled" : ""}`}
            onClick={onConfirm}
          >
            OK
          </button>
        </div>
      </div>
    </div>
  );
}
