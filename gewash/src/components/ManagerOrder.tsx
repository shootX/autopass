import React, { useState } from 'react'
import clsx from 'clsx'
import {
  managerCallIconUrl,
  managerRefreshIconUrl,
  managerConfirmedIconUrl,
  managerVectorIconUrl,
  managerCancelIconUrl,
} from '@/assets/staticUrls'

type Status = 'Confirm' | 'Rescheduled' | 'Expired' | 'Deleted' | 'New'

interface ManagerOrderProps {
  status?: Status
  localizedStatus: string
  date: string
  type: string
  customer: { name: string; phone: string }
  onDelete: () => void
  onReschedule?: () => void
  onConfirmed: () => void
}

const statusColorMap: Record<
  Status,
  {
    capBg: string;
    capText: string;
    labelBg: string;
    labelText: string;
  }
> = {
  Confirm: {
    capBg: "#B0EFBC",
    capText: "#14482F",
    labelBg: "#1E9E5A",
    labelText: "#FFFFFF",
  },
  Rescheduled: {
    capBg: "#A2ABA4",
    capText: "#14482F",
    labelBg: "#14482F",
    labelText: "#B5DD3A",
  },
  Expired: {
    capBg: "#FFC6C6",
    capText: "#14482F",
    labelBg: "#D64541",
    labelText: "#B5DD3A",
  },
  Deleted: {
    capBg: "#A2ABA4",
    capText: "#FFFFFF",
    labelBg: "#DCE1DB",
    labelText: "#A2ABA4",
  },
  New: {
    capBg: "#14482F",
    capText: "#B5DD3A",
    labelBg: "#B5DD3A",
    labelText: "#14482F",
  },
};


function getStatusStyle(status: Status): React.CSSProperties {
  const { labelBg, labelText } = statusColorMap[status]

  const correctedBg = status === 'Deleted' ? '#DCE1DB' : labelBg

  return {
    backgroundColor: correctedBg,
    color: labelText,
    borderRadius: '8px',
    padding: '6px 12px',
    fontWeight: 600,
    fontSize: '1rem',
  }
}

export default function ManagerOrder({
  status = 'New',
  localizedStatus,
  date,
  customer,
  type,
  onDelete,
  onReschedule,
  onConfirmed
}: ManagerOrderProps) {
  const isConfirmed = status === 'Confirm'
  const isNew = status === 'New'
  const isDeleted = status === 'Deleted'
  const capStyle = {
    backgroundColor: statusColorMap[status].capBg,
    color: statusColorMap[status].capText,
  }

  const renderControlButtons = () => {
    return (
      <div className='order-controll-right-box'>
        <a href={`tel:+${customer.phone}`}>
          <button className='order-btn'>
            <img src={managerCallIconUrl} alt='cutomerCall' />
          </button>
        </a>
        {!isConfirmed && onReschedule && (
          <button
            className='order-btn'
            onClick={onReschedule}
            disabled={isDeleted}
          >
            <img src={managerRefreshIconUrl} alt='refresh' />
          </button>
        )}
        <button className='order-btn' disabled={isDeleted} onClick={onConfirmed}>
          <img src={managerConfirmedIconUrl} alt='confirmed' />

        </button>
      </div>
    )
  }

  return (
    <div
      className={clsx("manager-order-container", { "gray-scale": isDeleted })}
    >
      <div className='order-cap' style={capStyle}>
        <h4 style={{ color: statusColorMap[status].capText }}>{date}</h4>
        <span className='order-status' style={getStatusStyle(status)}>
          {localizedStatus}
        </span>
      </div>

      <div className='order-info'>
        <div className='washing-type-container'>
          <img
            src={managerVectorIconUrl}
            alt='washing-type-icon'
          />
          <p className='washing-type'>{type}</p>
        </div>

        <div className='customer-order-info'>
          <p className='customer-order-name'>{customer.name}</p>
          <p className='customer-order-phone-number'>{customer.phone}</p>
        </div>

        <div className='order-controll-panel'>
          <div>
            <button
              className='order-btn cancel'
              onClick={onDelete}
              disabled={isDeleted}
            >
              <img
                src={managerCancelIconUrl}
                alt='cancel-btn'
              />
            </button>
          </div>

          {(isNew || isDeleted) && renderControlButtons()}

          {status !== "New" && !isDeleted && (
            <div className='order-controll-right-box'>
              <a href={`tel:+${customer.phone}`}>
                <button className='order-btn'>
                  <img
                    src={managerCallIconUrl}
                    alt='cutomerCall'
                  />
                </button>
              </a>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
